<?php
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

declare(strict_types=1);

namespace Export\Services;

use Atro\Core\EventManager\Event;
use Atro\Core\EventManager\Manager;
use Atro\Entities\Folder;
use Atro\Core\Exceptions\Error;
use Atro\Core\Exceptions\Exception;
use Espo\Core\Utils\Util;
use Atro\Entities\File;
use Espo\ORM\EntityCollection;
use Export\Core\Exceptions\NothingToExport;
use Export\Entities\ExportJob;
use Export\TemplateLoaders\AbstractTemplate;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;

class ExportTypeSimple extends AbstractExportType
{
    protected $fullCollection = null;

    public function runExport(ExportJob $exportJob): File
    {
        $this->getMemoryStorage()->set('exportJobId', $exportJob->get('id'));

        $attachmentCreatorName = 'export' . ucfirst($this->data['feed']['fileType']);
        if (!method_exists($this, $attachmentCreatorName)) {
            throw new Error('Unsupported file type.');
        }

        $attachment = $this->$attachmentCreatorName($exportJob);

        if ($exportJob->get('count') === 0) {
            $this->getEntityManager()->removeEntity($attachment);
            throw new NothingToExport($this->translate('noDataFound', 'exceptions', 'ExportFeed'));
        }

        $this->getEventManager()->dispatch('ExportTypeSimpleService', 'afterRunExport', new Event(['typeService' => $this, 'data' => $this->data, 'file' => $attachment]));

        return $attachment;
    }

    protected function getTemplateClassLoader(?string $templateLoaderName = null): AbstractTemplate
    {
        $className = 'Export\\TemplateLoaders\\TwigTemplate';

        if (!empty($templateLoaderName)) {
            $templateClassName = $this->getMetadata()->get(['app', 'templateLoaders', $templateLoaderName]);

            if (!empty($templateClassName) && is_a($templateClassName, AbstractTemplate::class, true)) {
                $templateClass = $this->getInjection('container')->get($templateClassName);

                if ($templateClass->isTemplateCompatible($this->data['feed'])) {
                    return $templateClass;
                }
            }
        }

        return $this->getInjection('container')->get($className);
    }

    public function renderTemplateContents(string $template, array $templateData, ?string $loaderName = null): string
    {
        $templateData['config'] = $this->getConfig()->getData();
        $templateData['feedData'] = $this->data['feed'];

        $templateLoader = $this->getTemplateClassLoader($loaderName);
        $templateLoader->addTemplate($template);
        $templateLoader->setData($templateData);

        return $templateLoader->render();
    }

    public function getFullCollection(): EntityCollection
    {
        if ($this->fullCollection === null) {
            $this->fullCollection = new EntityCollection();
            $offset = (int)$this->data['offset'];
            while (!empty($v = $this->getCollection($offset))) {
                $offset = $offset + $this->data['limit'];
                foreach ($v as $entity) {
                    $this->fullCollection->append($entity);
                }
            }
        }

        return $this->fullCollection;
    }

    public function createExportFileFolder(\Export\Entities\ExportFeed $exportFeed): Folder
    {
        $folder = $exportFeed->get('folder');

        if (empty($folder)) {
            /** @var \Atro\Repositories\Folder $folderRepo */
            $folderRepo = $this->getEntityManager()->getRepository('Folder');

            $root = $folderRepo->where(['code' => 'export_feeds'])->findOne();
            if (empty($root)) {
                $root = $folderRepo->get();
                $root->set([
                    'name'   => 'Export Feeds',
                    'hidden' => true,
                    'code'   => 'export_feeds'
                ]);
                $this->getEntityManager()->saveEntity($root);
            }

            $folder = $folderRepo->where(['code' => $exportFeed->get('id')])->findOne();
            if (empty($folder)) {
                $folder = $folderRepo->get();
                $folder->set([
                    'name'   => $exportFeed->get('name'),
                    'hidden' => true,
                    'code'   => $exportFeed->get('id')
                ]);
                $this->getEntityManager()->saveEntity($folder);
                $folderRepo->relate($folder, 'parents', $root);
            }
        }

        return $folder;
    }

    protected function exportJson(ExportJob $exportJob): File
    {
        if (!empty($this->data['feed']['separateJob'])) {
            $collection = $this->getCollection();
        } else {
            $collection = $this->getFullCollection();
        }

        $exportJob->set('count', $collection instanceof EntityCollection ? count($collection) : 0);

        $contents = $this->renderTemplateContents((string)$this->data['feed']['template'], ['entities' => $collection], $this->data['feed']['originTemplateName']);

        if (!empty($contents)) {
            $array = @json_decode(preg_replace("/}[\n\s]*,[\n\s]*]/", "}]", $contents), true);
            if (!empty($array)) {
                $contents = json_encode($array);
            }
        }

        if (empty($contents)) {
            $contents = '{}';
        }

        $input = new \stdClass();
        $input->name = $this->getExportFileName('json');
        $input->hidden = true;
        $input->folderId = $this->createExportFileFolder($exportJob->get('exportFeed'))->get('id');

        $fileData = $this->getService('File')->createFileViaContents($input, $contents);

        return $this->getEntityManager()->getRepository('File')->get($fileData['id']);
    }

    protected function exportSql(ExportJob $exportJob): File
    {
        if (!empty($this->data['feed']['separateJob'])) {
            $collection = $this->getCollection();
        } else {
            $collection = $this->getFullCollection();
        }

        $exportJob->set('count', $collection instanceof EntityCollection ? count($collection) : 0);

        $contents = $this->renderTemplateContents((string)$this->data['feed']['template'], ['entities' => $collection], $this->data['feed']['originTemplateName']);

        $contents = join(
            "\n", array_map(function ($query) {
                return trim($query);
            }, \SqlFormatter::splitQuery($contents))
        );

        $input = new \stdClass();
        $input->name = $this->getExportFileName('sql');
        $input->hidden = true;
        $input->folderId = $this->createExportFileFolder($exportJob->get('exportFeed'))->get('id');

        $contents = empty($contents) ? " " : $contents;
        $fileData = $this->getService('File')->createFileViaContents($input, $contents);

        return $this->getEntityManager()->getRepository('File')->get($fileData['id']);
    }

    protected function exportXml(ExportJob $exportJob): File
    {
        if (!empty($this->data['feed']['separateJob'])) {
            $collection = $this->getCollection();
        } else {
            $collection = $this->getFullCollection();
        }

        $exportJob->set('count', $collection instanceof EntityCollection ? count($collection) : 0);

        $contents = $this->renderTemplateContents((string)$this->data['feed']['template'], ['entities' => $collection], $this->data['feed']['originTemplateName']);

        $input = new \stdClass();
        $input->name = $this->getExportFileName('xml');
        $input->hidden = true;
        $input->folderId = $this->createExportFileFolder($exportJob->get('exportFeed'))->get('id');

        $fileData = $this->getService('File')->createFileViaContents($input, $contents);

        $file = $this->getEntityManager()->getRepository('File')->get($fileData['id']);

        $this->validateXml($file, $exportJob);

        return $file;
    }

    protected function validateXml(File $file, ExportJob $exportJob)
    {
        $dom = new \DOMDocument();
        $dom->load($file->getFilePath());
        libxml_use_internal_errors(true);
        $sxe = new \SimpleXMLElement($file->getFilePath(), 0, true);
        $schemaLocation = $sxe->attributes('xsi', true)->schemaLocation;
        $regex = '/https?\:\/\/[^\" ]+/i';
        preg_match($regex, (string)$schemaLocation, $matches);
        if (empty($matches[0])) {
            return;
        }

        $path = tempnam(sys_get_temp_dir(), "xsd");

        if ($this->downloadXsd($matches[0], $path) != "200") {
            return;
        }

        if (!$dom->schemaValidate($path)) {
            $logs = [];
            $validationFailed = false;

            foreach (libxml_get_errors() as $error) {
                $logs[] = $this->buildXmlLog($error);
                if ($error->level == LIBXML_ERR_ERROR || $error->level == LIBXML_ERR_FATAL) {
                    $validationFailed = true;
                }
            }
            libxml_clear_errors();

            $exportJob->set('stateMessage', $this->translate('xmlValidationFailed', 'messages', 'ExportJob') . "\n" . join("\n", $logs));
            if ($validationFailed) {
                $exportJob->set('state', 'Failed');
            }
        }
        unlink($path);
    }

    protected function downloadXsd($url, $path)
    {
        $options = array(
            CURLOPT_FILE           => fopen($path, 'w'),
            CURLOPT_TIMEOUT        => 28800,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_URL            => $url
        );

        $ch = curl_init();
        curl_setopt_array($ch, $options);
        curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return $code;
    }

    function buildXmlLog($error): string
    {
        $log = "";
        switch ($error->level) {
            case LIBXML_ERR_WARNING:
                $log .= "Warning $error->code: ";
                break;
            case LIBXML_ERR_ERROR:
                $log .= "Error $error->code: ";
                break;
            case LIBXML_ERR_FATAL:
                $log .= "Fatal Error $error->code: ";
                break;
        }
        $log .= trim($error->message) . " on line $error->line";

        return $log;
    }

    protected function exportCsv(ExportJob $exportJob): File
    {
        $exportFeed = $exportJob->get('exportFeed');
        if(empty($exportFeed)){
            $exportFeed = $this->getEntityManager()->getEntity('ExportFeed');
            $exportFeed->set('id', Util::generateId());
            $exportFeed->set('name', Util::generateUniqueHash());
        }

        $input = new \stdClass();
        $input->name = $this->getExportFileName('csv');
        $input->hidden = true;
        $input->folderId = $this->createExportFileFolder($exportFeed)->get('id');

        $this->initZipArchive([$this->data['feed']['data']['configuration']]);

        $data = $this->createCacheFile();

        $exportJob->set('count', $data['count']);
        $exportJob->set('data', array_merge($exportJob->getData(), $data));

        // create tmp CSV file
        $fileName = self::TMP_DIR . DIRECTORY_SEPARATOR . $this->data['exportJobId'] . DIRECTORY_SEPARATOR . Util::generateUniqueHash() . DIRECTORY_SEPARATOR . $input->name;
        $this->createDir($fileName);
        $this->storeCsvFile($exportJob->getData(), $fileName);

        $fileData = $this->getService('File')->moveLocalFileToFileEntity($input, $fileName);

        // delete tmp file
        if(file_exists($fileName)){
            unlink($fileName);
        }

        $file = $this->getEntityManager()->getRepository('File')->get($fileData['id']);

        return $this->exportAsZip($file);
    }

    protected function canBuildZipArchive(array $configurations)
    {
        foreach ($configurations as $configuration) {
            foreach ($configuration as $field) {
                if ($field['zip']) {
                    return true;
                }
            }
        }
        return false;
    }

    protected function initZipArchive(array $configurations)
    {
        if (!$this->canBuildZipArchive($configurations)) {
            return false;
        }

        $zipDir = $this->getZipTmpDir();

        Util::createDir($zipDir);

        $fileName = $zipDir . DIRECTORY_SEPARATOR . $this->getExportFileName('zip');

        $this->zipArchive = new \ZipArchive();
        if ($this->zipArchive->open($fileName, \ZipArchive::CREATE) !== true) {
            throw new Exception("cannot open archive $fileName\n");
        }
        $this->zipFileName = $fileName;

        return true;
    }

    protected function exportXlsx(ExportJob $exportJob): File
    {
        $metadata = $this->getMetadata();

        if (!empty($this->data['feed']['sheets'])) {
            $sheets = $this->data['feed']['sheets'];
        } else {
            $sheets = [
                [
                    'name'          => 'Sheet',
                    'configuration' => $this->data['feed']['data']['configuration'],
                    'entity'        => $this->data['feed']['entity'],
                    'data'          => $this->data['feed']['data'],
                ]
            ];
        }

        $exportFeed = $exportJob->get('exportFeed');
        if(empty($exportFeed)){
            $exportFeed = $this->getEntityManager()->getEntity('ExportFeed');
            $exportFeed->set('id', Util::generateId());
            $exportFeed->set('name', Util::generateUniqueHash());
        }

        $input = new \stdClass();
        $input->name = $this->getExportFileName('xlsx');
        $input->hidden = true;
        $input->folderId = $this->createExportFileFolder($exportFeed)->get('id');

        $this->initZipArchive(
            array_map(function ($sheet) {
                return $sheet['configuration'];
            }, $sheets)
        );

        $fileName = self::TMP_DIR . DIRECTORY_SEPARATOR . $exportJob->get('id') . DIRECTORY_SEPARATOR . Util::generateUniqueHash() . $input->name;

        $this->createDir($fileName);

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();

        $count = 0;
        foreach ($sheets as $k => $sheet) {
            $this->data['feed']['data']['configuration'] = $sheet['configuration'];
            $this->data['feed']['entity'] = $sheet['entity'];
            $this->data['feed']['data']['entity'] = $sheet['entity'];
            $this->data['feed']['data']['whereScope'] = $sheet['entity'];
            $this->data['feed']['data']['where'] = $sheet['data']['where'] ?? [];
            if (count($sheets) > 1 && !empty($this->zipArchive) && $this->canBuildZipArchive([$sheet['configuration']])) {
                $base_dir = $sheet['name'] . '/';
                $this->data['zipPath'] = $base_dir;
                if (!$this->zipArchive->locateName($base_dir)) {
                    $this->zipArchive->addEmptyDir($base_dir);
                }
            }

            $this->convertor = $this->getDataConvertor();
            $data = $this->createCacheFile();
            $count += $data['count'];

            // prepare CSV filename
            $pathParts = explode(DIRECTORY_SEPARATOR, $fileName);
            array_pop($pathParts);
            $pathParts[] = Util::generateUniqueHash() . '.csv';
            $csvFileName = implode('/', $pathParts);

            $this->storeCsvFile($data, $csvFileName);

            // prepare CSV reader
            $reader = new \PhpOffice\PhpSpreadsheet\Reader\Csv();
            $reader->setDelimiter($this->getDelimiter());
            $reader->setEnclosure($this->getEnclosure());

            $myWorkSheet = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($spreadsheet, $sheet['name']);
            $spreadsheet->addSheet($myWorkSheet, $k);

            // load a CSV file and save as a XLS
            $reader->setSheetIndex($k);
            $reader->loadIntoExisting($csvFileName, $spreadsheet);

            $entityDefs = $metadata->get(['entityDefs', $sheet['entity']]);
            $workSheet = $spreadsheet->getSheet($k);
            $startRow = 1;
            if ($sheet['data']['isFileHeaderRow']) {
                $startRow = 2;
            }

            // skip empty worksheets
            if ($startRow <= $workSheet->getHighestRow()) {
                foreach ($workSheet->getColumnIterator() as $configIndex => $column) {
                    $index = Coordinate::columnIndexFromString($configIndex) - 1;
                    if (!isset($sheet['configuration'][$index])) {
                        continue;
                    }

                    $sheetCol = $sheet['configuration'][$index];
                    $decimalMark = $sheetCol['decimalMark'];
                    $thousandSeparator = $sheetCol['thousandSeparator'];

                    switch ($sheetCol['type']) {
                        case 'Field':
                            $cellType = $entityDefs['fields'][$sheetCol['field']]['type'] ?? null;
                            if (in_array($cellType, ['varchar', 'text', 'enum', 'multiEnum', 'extensibleMultiEnum', 'wysiwyg'])) {
                                foreach ($column->getCellIterator($startRow) as $cell) {
                                    $cell->setValueExplicit($cell->getValue(), DataType::TYPE_STRING2);
                                }
                            } else {
                                if ($cellType == 'float') {
                                    foreach ($column->getCellIterator($startRow) as $cell) {
                                        $this->processXlsxNumericCell($cell, $decimalMark, $thousandSeparator);
                                    }
                                } else {
                                    if ($cellType == 'currency') {
                                        foreach ($column->getCellIterator($startRow) as $cell) {
                                            // if currency field exported using value only mask
                                            if (preg_match("/^[\d\W]+$/", (string)$cell->getValue())) {
                                                $this->processXlsxNumericCell($cell, $decimalMark, $thousandSeparator);
                                            }
                                        }
                                    } else {
                                        if ($cellType == 'int' && $thousandSeparator) {
                                            foreach ($column->getCellIterator($startRow) as $cell) {
                                                if (is_string($cell->getValue())) {
                                                    $this->processXlsxNumericCell($cell, $decimalMark, $thousandSeparator);
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                            break;
                        case 'Attribute':
                            if (in_array($sheetCol['attributeValue'], ['valueString', 'value'])) {
                                foreach ($column->getCellIterator($startRow) as $cell) {
                                    $cell->setValueExplicit($cell->getValue(), DataType::TYPE_STRING2);
                                }
                            } else {
                                if (in_array($sheetCol['attributeValue'], ['valueNumeric', 'valueFrom', 'valueTo'])) {
                                    foreach ($column->getCellIterator($startRow) as $cell) {
                                        $this->processXlsxNumericCell($cell, $decimalMark, $thousandSeparator);
                                    }
                                }
                            }
                            break;
                        default:
                            break;
                    }
                }
            }

            // delete csv file
            unlink($csvFileName);
        }

        try {
            // delete default sheet
            $spreadsheet->removeSheetByIndex(count($sheets));
        } catch (\Throwable $e) {
            // ignore
        }

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save($fileName);

        $fileData = $this->getService('File')->moveLocalFileToFileEntity($input, $fileName);

        // delete tmp file
        if(file_exists($fileName)){
            unlink($fileName);
        }

        $exportJob->set('count', $count);

        $file = $this->getEntityManager()->getRepository('File')->get($fileData['id']);

        return $this->exportAsZip($file);
    }

    private function processXlsxNumericCell(Cell $cell, $decimalMark, $thousandSeparator): void
    {
        $cellValue = (string)$cell->getValue();

        if ($thousandSeparator && str_contains($cellValue, $thousandSeparator)) {
            $cellValue = str_replace($thousandSeparator, "", $cellValue);
        }
        if ($decimalMark && str_contains($cellValue, $decimalMark)) {
            $cellValue = str_replace($decimalMark, ".", $cellValue);
        }

        if (is_numeric($cellValue)) {
            $cell->setValueExplicit($cellValue, DataType::TYPE_NUMERIC);
            if ($thousandSeparator && $cellValue != 0) {
                // hide decimal part for integers
                $format = filter_var($cellValue, FILTER_VALIDATE_INT) ? "#,##" : "#,##0." . str_repeat('0', strcspn(strrev($cellValue), '.'));
                $cell->getStyle()->getNumberFormat()->setFormatCode($format);
            }
        }
    }

    protected function exportAsZip(File $file): File
    {
        if (!empty($this->zipArchive)) {
            $this->zipArchive->addFile($file->getFilePath(), $file->get('name'));
            $this->zipArchive->close();

            $input = new \stdClass();
            $input->name = $this->getExportFileName('zip');
            $input->hidden = true;
            $input->folderId = $file->get('folderId');

            $this->getEntityManager()->removeEntity($file);

            $fileData = $this->getService('File')->moveLocalFileToFileEntity($input, $this->zipFileName);

            //  delete tmp zip file
            Util::removeDir($this->getZipTmpDir());

            return $this->getEntityManager()->getRepository('File')->get($fileData['id']);
        }

        return $file;
    }

    protected function prepareColumns(array $data): array
    {
        $columns = [];

        $cacheFile = fopen($data['fullFileName'], "r");
        while (($line = fgets($cacheFile)) !== false) {
            if (empty($line)) {
                continue;
            }
            $json = @json_decode($line, true);
            if (!is_array($json)) {
                continue;
            }

            foreach ($json as $rowNumber => $colData) {
                $n = 0;
                foreach ($colData as $colName => $colValue) {
                    $columns[$rowNumber . '_' . $colName] = [
                        'number' => $rowNumber,
                        'pos'    => $rowNumber * 1000 + $n,
                        'name'   => $colName
                    ];
                    $n++;
                }
            }
        }
        fclose($cacheFile);

        $result = [];

        // sorting columns
        $number = 0;
        while (count($columns) > 0) {
            foreach ($columns as $k => $row) {
                if ($row['number'] == $number) {
                    $result[] = $row;
                    unset($columns[$k]);
                }
            }
            $number++;
        }

        return $result;
    }

    protected function storeCsvFile(array $data, string $fileName): void
    {
        $columns = $this->prepareColumns($data);

        $this->createDir($fileName);

        $delimiter = $this->getDelimiter();
        $enclosure = $this->getEnclosure();
        $useQuoteForAllValue = !empty($this->data['feed']['useQuoteForAll']);

        $fp = fopen($fileName, "w");

        // prepare header
        if ($this->data['feed']['isFileHeaderRow']) {
            fputcsv($fp, array_column($columns, 'name'), $delimiter, $enclosure);
        }

        $cacheFile = fopen($data['fullFileName'], "r");
        while (($line = fgets($cacheFile)) !== false) {
            if (empty($line)) {
                continue;
            }

            $rowData = @json_decode($line, true);
            if (!is_array($rowData)) {
                continue;
            }

            $resultRow = [];
            foreach ($columns as $pos => $columnData) {
                $resultRow[$pos] = isset($rowData[$columnData['number']][$columnData['name']]) ? $rowData[$columnData['number']][$columnData['name']] : null;
            }

            if($useQuoteForAllValue) {
                $enclosedRow = array_map(fn($value) => $enclosure . $value. $enclosure, $resultRow);
                fwrite($fp, implode($delimiter, $enclosedRow) . (feof($cacheFile) ? PHP_EOL : "\n"));
            }else{
                fputcsv($fp, $resultRow, $delimiter, $enclosure);
            }
        }
        fclose($cacheFile);

        rewind($fp);
        fclose($fp);

        // remove cache file
        unlink($data['fullFileName']);
    }

    protected function createDir(string $fileName): void
    {
        $parts = explode('/', $fileName);
        array_pop($parts);
        $dir = implode('/', $parts);

        if (!file_exists($dir)) {
            mkdir($dir, 0777, true);
            sleep(1);
        }
    }

    public function getUrlColumns(): array
    {
        $urlColumns = [];
        $data = $this->data['feed']['data'];

        foreach ($data['configuration'] as $row) {
            if (is_array($row['exportBy']) && $row['exportBy'][0] === "downloadUrl") {
                $urlColumns[] = $row['column'];
            }
        }
        return $urlColumns;
    }

    public function exportEasyCatalogJson(): array
    {
        $this->convertor = $this->getDataConvertor();
        $data = $this->createCacheFile();
        $columns = $this->prepareColumns($data);

        $result = [];
        $cacheFile = fopen($data['fullFileName'], "r");
        while (($line = fgets($cacheFile)) !== false) {
            if (empty($line)) {
                continue;
            }

            $rowData = @json_decode($line, true);
            if (!is_array($rowData)) {
                continue;
            }

            $resultRow = [];
            foreach ($columns as $pos => $columnData) {
                $resultRow[$columnData['name']] = isset($rowData[$columnData['number']][$columnData['name']]) ? $rowData[$columnData['number']][$columnData['name']] : null;
            }
            $result[] = $resultRow;

        }
        fclose($cacheFile);

        return $result;
    }

    protected function getEventManager(): Manager
    {
        return $this->getContainer()->get('eventManager');
    }
}
