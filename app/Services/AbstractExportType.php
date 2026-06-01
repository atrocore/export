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

use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Container;
use Atro\Core\Exceptions\Error;
use Atro\ORM\DB\RDB\Mapper;
use Atro\Repositories\SavedSearch;
use Doctrine\DBAL\Query\QueryBuilder;
use Espo\Core\Services\Base;
use Atro\Core\Twig\Twig;
use Atro\Core\Utils\Config;
use Espo\Core\Utils\Json;
use Atro\Core\Utils\Language;
use Espo\Core\Utils\Metadata;
use Atro\Core\Utils\Util;
use Atro\Entities\File;
use Espo\ORM\Entity;
use Espo\ORM\EntityCollection;
use Espo\ORM\EntityManager;
use Espo\ORM\IEntity;
use Espo\Services\Record;
use Export\DataConvertor\Convertor;
use Export\Entities\ExportJob;

abstract class AbstractExportType extends Base
{
    public const TMP_DIR = 'data' . DIRECTORY_SEPARATOR . '.tmp-export';

    protected const ATTRIBUTE_CHUNK_SIZE = 200;

    public const PRIORITIES = [
        'Low'     => 50,
        'Normal'  => 100,
        'Crucial' => 140,
        'High'    => 150,
    ];

    protected array $data;

    protected Convertor $convertor;

    protected ?\ZipArchive $zipArchive = null;
    protected $zipFileName = null;

    public static function getAllFieldsConfiguration(string $scope, Metadata $metadata, Language $language): array
    {
        $configuration = [['field' => 'id', 'language' => 'main', 'column' => 'ID']];

        /** @var array $allFields */
        $allFields = $metadata->get(['entityDefs', $scope, 'fields'], []);

        foreach ($allFields as $field => $data) {
            if (!empty($data['exportDisabled']) || !empty($data['disabled'])
                || in_array(
                    $data['type'], ['linkParent', 'currencyConverted', 'available-currency', 'file', 'attachmentMultiple']
                )) {
                continue 1;
            }

            $row = [
                'field'    => $field,
                'language' => 'main',
                'column'   => $language->translate($field, 'fields', $scope),
            ];

            if (!empty($data['multilangLocale'])) {
                $row['field']    = $data['multilangField'];
                $row['language'] = $data['multilangLocale'];
            }

            if (isset($configuration[$row['column']])) {
                continue 1;
            }

            if (in_array($data['type'], ['link', 'linkMultiple'])) {
                $row['exportBy'] = ['id'];
            }

            if (in_array($data['type'], ['extensibleEnum', 'extensibleMultiEnum'])) {
                $row['exportBy'] = ['name'];
            }

            if ($data['type'] === 'linkMultiple') {
                $row['exportIntoSeparateColumns'] = false;
                $row['offsetRelation']            = 0;
                $row['limitRelation']             = 20;
                $row['sortFieldRelation']         = 'id';
                $row['sortOrderRelation']         = '1'; // ASC
            }

            if ($data['type'] === 'currency') {
                $row['mask'] = "{{value}} {{currency}}";
            }

            if ($data['type'] === 'unit') {
                $row['mask'] = "{{value}} {{unit}}";
            }

            $configuration[$row['column']] = $row;
        }

        return array_values($configuration);
    }

    public function export(array $data, ExportJob $exportJob): File
    {
        $this->setData($data);
        $this->convertor = $this->getDataConvertor();

        return $this->runExport($exportJob);
    }

    abstract public function runExport(ExportJob $exportJob): File;

    public function setData(array $data): void
    {
        $this->data = Json::decode(Json::encode($data), true);
    }

    protected function getExportFileName(string $extension): string
    {
        $fileName = preg_replace("/[^a-z0-9.!?]/", '', mb_strtolower($this->data['feed']['name']));
        if (!empty($this->data['iteration'])) {
            $fileName .= '_' . $this->data['iteration'];
        }

        $fileName .= '_' . date('YmdHis');


        if (!empty($this->data['feed']['fileNameMask'])) {
            $data = [
                'feed'     => $this->data['feed'],
                'fileName' => $fileName,
            ];
            if (array_key_exists('iteration', $this->data)) {
                $data['iteration'] = $this->data['iteration'];
            }
            $newFileName = $this->getTwig()->renderTemplate((string)$this->data['feed']['fileNameMask'], $data);

            if (!empty($newFileName)) {
                $fileName = $newFileName;
            }
        }


        return $fileName . '.' . $extension;
    }

    protected function prepareRow(array $row): array
    {
        $feedData = $this->data['feed']['data'];

        $row['delimiter']                 = !empty($feedData['delimiter']) ? $feedData['delimiter'] : ',';
        $row['emptyValue']                = !empty($feedData['emptyValue']) ? $feedData['emptyValue'] : '';
        $row['nullValue']                 = array_key_exists('nullValue', $feedData) ? $feedData['nullValue'] : 'Null';
        $row['markForNoRelation']         = array_key_exists('markForNoRelation', $feedData) ? $feedData['markForNoRelation'] : 'Null';
        $row['fieldDelimiterForRelation'] = !empty($feedData['fieldDelimiterForRelation']) ? $feedData['fieldDelimiterForRelation'] : '|';
        $row['entity']                    = $feedData['entity'];
        $row['decimalMark']               = $feedData['decimalMark'];
        $row['thousandSeparator']         = $feedData['thousandSeparator'];


        return $row;
    }

    protected function getDataConvertor(): Convertor
    {
        $className = "Export\\DataConvertor\\{$this->data['feed']['data']['entity']}Convertor";

        if (!class_exists($className)) {
            $className = Convertor::class;
        }

        if (!is_a($className, Convertor::class, true)) {
            throw new Error($className . ' should be instance of ' . Convertor::class);
        }

        return new $className($this->getContainer());
    }

    protected function getSelectParams(): array
    {

        $params = [
            'sortBy'      => 'id',
            'asc'         => true,
            'offset'      => 0,
            'maxSize'     => 1,
            'where'       => $this->getWhere(),
            'withDeleted' => !empty($this->data['feed']['data']['withDeleted']),
        ];

        return $params;
    }

    public function queryCallback(QueryBuilder $qb, IEntity $relEntity, array $params, Mapper $mapper): void
    {
        foreach ($this->getMemoryStorage()->get('exportConfiguration') ?? [] as $item) {
            $this->getDataConvertor()
                ->createFieldConverter($this->getDataConvertor()->getConfigurationItemType($item))
                ->queryCallback($this->getContainer(), $qb, $mapper, $item);
        }
    }

    protected function prepareSelectParams(): array
    {
        $params                = $this->getSelectParams();
        $params['withDeleted'] = !empty($this->data['feed']['data']['withDeleted']);

        if (!empty($this->data['feed']['sortOrderField'])) {
            $params['sortBy'] = $this->data['feed']['sortOrderField'];
            if ($this->getMetadata()->get(['entityDefs', $this->data['feed']['entity'], 'fields', $params['sortBy'], 'type']) === 'link') {
                $params['sortBy'] .= 'Id';
            }
            $params['asc'] = true;
            if (!empty($this->data['feed']['sortOrderDirection']) && $this->data['feed']['sortOrderDirection'] !== 'ASC') {
                $params['asc'] = false;
            }
        }

        return $params;
    }

    public function getTotal(): int
    {
        if (!$this->getAcl()->check($this->data['feed']['entity'], 'read')) {
            return 0;
        }

        $params              = $this->prepareSelectParams();
        $params['totalOnly'] = true;

        return $this->getEntityService()->findEntities($params)['total'] ?? 0;
    }

    protected function collectAttributeIds(): array
    {
        $entityName = $this->data['feed']['entity'] ?? null;
        if (empty($entityName) || !$this->getMetadata()->get("scopes.$entityName.hasAttribute")) {
            return [];
        }

        $ids = [];
        foreach ($this->data['feed']['data']['configuration'] ?? [] as $item) {
            if (!empty($item['entityAttributeId'])) {
                $ids[$item['entityAttributeId']] = true;
            }
        }

        return array_keys($ids);
    }

    /**
     * Loads attribute values onto a collection in chunks to avoid generating too many SQL JOINs.
     * Only called when the number of attribute IDs exceeds ATTRIBUTE_CHUNK_SIZE.
     *
     * For each chunk a second findEntities is issued with:
     *   - WHERE id IN (page entity ids) — no original filters
     *   - select: ['id']               — suppress re-fetching base columns
     *   - attributesIds: chunk          — only this batch of attribute JOINs
     *
     * Attribute field definitions and values from each chunk entity are then merged
     * directly onto the matching base entity.
     */
    protected function enrichCollectionWithAttributes(EntityCollection $collection, array $attributeIds): void
    {
        if (empty($attributeIds) || count($collection) === 0) {
            return;
        }

        $entityIds = [];
        $entityMap = [];
        foreach ($collection as $entity) {
            $id             = $entity->get('id');
            $entityIds[]    = $id;
            $entityMap[$id] = $entity;
        }

        foreach (array_chunk($attributeIds, self::ATTRIBUTE_CHUNK_SIZE) as $chunk) {
            $chunkResult = $this->getEntityService()->findEntities([
                'where'         => [['attribute' => 'id', 'type' => 'in', 'value' => $entityIds]],
                'disableCount'  => true,
                'attributesIds' => $chunk,
                'select'        => ['id'],
            ]);

            foreach ($chunkResult['collection'] ?? [] as $chunkEntity) {
                $baseEntity = $entityMap[$chunkEntity->get('id')] ?? null;
                if ($baseEntity === null) {
                    continue;
                }

                // Merge field definitions from both registries, set values for each
                // attribute field found in the chunk entity's fields map.
                foreach ($chunkEntity->fields as $key => $defs) {
                    if (empty($defs['attributeId']) || !in_array($defs['attributeId'], $chunk)) {
                        continue;
                    }
                    $baseEntity->fields[$key] = $defs;
                    $baseEntity->set($key, $chunkEntity->get($key));
                }

                foreach ($chunkEntity->entityDefs['fields'] as $key => $defs) {
                    if (empty($defs['attributeId']) || !in_array($defs['attributeId'], $chunk)) {
                        continue;
                    }
                    $baseEntity->entityDefs['fields'][$key] = $chunkEntity->entityDefs['fields'][$key];
                }

                $baseEntity->set('attributesDefs', array_merge(
                    $baseEntity->get('attributesDefs') ?? [],
                    $chunkEntity->get('attributesDefs') ?? []
                ));
            }

            unset($chunkResult);
            gc_collect_cycles();
        }
    }

    protected function getRecords(int $offset, int $limit): array
    {
        if (!$this->getAcl()->check($this->data['feed']['entity'], 'read')) {
            return [];
        }

        $params                        = $this->prepareSelectParams();
        $params['disableCount']        = true;
        $params['offset']              = $offset;
        $params['maxSize']             = $limit;
        $params['subQueryCallbacks'][] = [$this, 'queryCallback'];

        $attributeIds = $this->collectAttributeIds();

        if (count($attributeIds) <= self::ATTRIBUTE_CHUNK_SIZE && !empty($attributeIds)) {
            $params['attributesIds'] = $attributeIds;
        }

        $result = $this->getEntityService()->findEntities($params);

        if (!isset($result['collection'])) {
            return $result['list'] ?? [];
        }

        if (count($attributeIds) > self::ATTRIBUTE_CHUNK_SIZE) {
            $this->enrichCollectionWithAttributes($result['collection'], $attributeIds);
        }

        $list = [];
        foreach ($result['collection'] as $entity) {
            $list[] = array_merge($entity->toArray(), ['_entity' => $entity]);
        }

        return $list;
    }

    public function getCollection(int $offset = null): ?EntityCollection
    {
        if (!$this->getAcl()->check($this->data['feed']['entity'], 'read')) {
            return null;
        }

        if (!empty($this->data['entityIds'])) {
            return $this->getCollectionFromIds($this->data['entityIds']);
        }

        if ($offset === null) {
            $offset = $this->data['offset'];
        }

        $params                        = $this->getSelectParams();
        $params['offset']              = $offset;
        $params['maxSize']             = $this->data['limit'];
        $params['withDeleted']         = !empty($this->data['feed']['data']['withDeleted']);
        $params['disableCount']        = true;
        $params['subQueryCallbacks'][] = [$this, 'queryCallback'];

        if (!empty($this->data['feed']['sortOrderField'])) {
            $params['sortBy'] = $this->data['feed']['sortOrderField'];
            if ($this->getMetadata()->get(['entityDefs', $this->data['feed']['entity'], 'fields', $params['sortBy'], 'type']) === 'link') {
                $params['sortBy'] .= 'Id';
            }
            $params['asc'] = true;
            if (!empty($this->data['feed']['sortOrderDirection']) && $this->data['feed']['sortOrderDirection'] !== 'ASC') {
                $params['asc'] = false;
            }
        }

        $attributeIds = $this->collectAttributeIds();

        if (count($attributeIds) <= self::ATTRIBUTE_CHUNK_SIZE && !empty($attributeIds)) {
            $params['attributesIds'] = $attributeIds;
        }

        $result = $this->getEntityService()->findEntities($params);
        if (!isset($result['collection']) || count($result['collection']) === 0) {
            return null;
        }

        if (count($attributeIds) > self::ATTRIBUTE_CHUNK_SIZE) {
            $this->enrichCollectionWithAttributes($result['collection'], $attributeIds);
        }

        return $result['collection'];
    }

    /**
     * Function creates separate cache file. Major worker will collect all parts into ine file.
     *
     * @return array
     * @throws BadRequest
     * @throws Error
     */
    public function createCacheChunk(): array
    {
        $this->convertor = $this->getDataConvertor();

        $tmpDir = self::TMP_DIR . DIRECTORY_SEPARATOR . $this->data['exportJobId'] . DIRECTORY_SEPARATOR . Util::generateUniqueHash();
        Util::createDir($tmpDir);
        $fileName = Util::generateUniqueHash() . ".txt";

        $configuration = [];
        foreach ($this->data['feed']['data']['configuration'] as $rowNumber => $row) {
            $configuration[$rowNumber] = $this->prepareRow($row);
        }

        $this->getMemoryStorage()->set('exportConfiguration', $configuration);

        $fullFileName = $tmpDir . DIRECTORY_SEPARATOR . $fileName;

        // clearing file if it needs
        file_put_contents($fullFileName, '');

        $file = fopen($fullFileName, 'a');

        $records = $this->getRecords($this->data['offset'], $this->data['limit']);

        $files = [];

        if (!empty($records)) {
            $this->getMemoryStorage()->set('exportRecordsPartOffset', $this->data['offset']);
            $this->getMemoryStorage()->set('exportRecordsPart', $records);
            foreach ($records as $record) {
                $rowData = [];
                foreach ($configuration as $row) {
                    $result = $this->convertor->convert($record, $row);
                    if ($row['zip'] && isset($result['__fileEntities'])) {
                        foreach ($result['__fileEntities'] as $fileEntity) {
                            $path = $fileEntity->findOrCreateLocalFilePath($this->getZipTmpDir());
                            if (!file_exists($path)) {
                                throw new BadRequest("File '{$path}' does not exist.");
                            }
                            $files[] = [
                                'column'           => $row['column'],
                                'path'             => $path,
                                'fileNameTemplate' => $row['fileNameTemplate'] ?? null,
                                'templateData'     => [
                                    'entityId' => $record['id'],
                                ],
                            ];
                        }
                        unset($result['__fileEntities']);
                    }
                    $rowData[] = $result;
                }
                fwrite($file, Json::encode($rowData) . PHP_EOL);
            }
        }
        fclose($file);

        return [
            'fileName' => $fullFileName,
            'files'    => $files,
        ];
    }

    /**
     * Function split major job to separate jobs. When separate jobs will be finished major jobs create major cache file from the chunks cache files. That was developed for increase export speed.
     *
     * @param int $total
     * @return array
     * @throws BadRequest
     * @throws Error
     */
    protected function createCacheFileByChunks(int $total): array
    {
        $limit       = $this->data['limit'];
        $offset      = $this->data['offset'];
        $maxWorkers  = $this->data['feed']['maxWorkers'] ?? null;
        $exportJobId = $this->data['exportJobId'];

        $priority = empty($this->data['feed']['priority']) ? 'Normal' : (string)$this->data['feed']['priority'];
        $jobs     = new EntityCollection();
        $jobIds   = [];
        $i        = 1;

        while ($offset < $total) {
            if ($maxWorkers !== null && $maxWorkers > 0) {
                while ($this->getAmountOfAlreadyPendingChunks($exportJobId) >= $maxWorkers) {
                    sleep(1);
                }
            }

            $jobName = $this->data['feed']['name'] . " Chunk #$i";

            $subData             = $this->data;
            $subData['chunkJob'] = true;
            $subData['offset']   = $offset;

            $jobEntity = $this->getEntityManager()->getEntity('Job');
            $jobEntity->set([
                'name'     => $jobName,
                'type'     => 'ExportChunk',
                'payload'  => $subData,
                'priority' => self::PRIORITIES[$priority]
            ]);
            $this->getEntityManager()->saveEntity($jobEntity);

            $jobs->append($jobEntity);
            $jobIds[] = $jobEntity->get('id');
            $offset   = $offset + $limit;
            $i++;
        }

        if ($jobs->count() == 0) {
            throw new Error("Something wrong. System can't create any export chunk job.");
        }

        while (true) {
            if ($jobs->count() == 0) {
                break;
            }

            $success = true;
            $jobs    = $this->getEntityManager()->getRepository('Job')->findByIds($jobIds);
            foreach ($jobs as $job) {
                if ($job->get('status') === 'Failed') {
                    throw new BadRequest($job->get('message'));
                }

                if ($job->get('status') === 'Canceled') {
                    throw new Error("Export chunk job has been canceled.");
                }

                if ($job->get('status') !== 'Success') {
                    $success = false;
                }
            }

            if ($success) {
                break;
            }

            sleep(3);
        }

        $tmpDir = self::TMP_DIR . DIRECTORY_SEPARATOR . $this->data['exportJobId'] . DIRECTORY_SEPARATOR . Util::generateUniqueHash();
        Util::createDir($tmpDir);
        $fileName = Util::generateUniqueHash() . ".txt";

        $res = [
            'configuration' => [],
            'fullFileName'  => $tmpDir . DIRECTORY_SEPARATOR . $fileName,
            'count'         => 0,
        ];

        foreach ($this->data['feed']['data']['configuration'] as $rowNumber => $row) {
            $res['configuration'][$rowNumber] = $this->prepareRow($row);
        }

        $this->getMemoryStorage()->set('exportConfiguration', $res['configuration']);

        // clearing file if it needs
        file_put_contents($res['fullFileName'], '');

        $file = fopen($res['fullFileName'], 'a');

        $fileNames    = [];
        $zipFilesData = [];
        foreach ($jobs as $job) {
            if (empty($job->get('payload')->chunkFileName)) {
                continue;
            }
            $fileNames[$job->get('payload')->offset] = $job->get('payload')->chunkFileName;
            foreach ($job->get('payload')->files ?? [] as $fileRec) {
                $zipFilesData[] = json_decode(json_encode($fileRec), true);
            }
        }
        ksort($fileNames);

        foreach ($fileNames as $fileName) {
            $cacheFile = fopen($fileName, "r");
            while (($line = fgets($cacheFile)) !== false) {
                if (empty($line)) {
                    continue;
                }
                fwrite($file, $line . PHP_EOL);
                $res['count']++;
            }
            fclose($cacheFile);

            // delete chunk cache file
            @unlink($fileName);
        }
        fclose($file);

        if (!empty($zipFilesData)) {
            $fileNumber = 0;

            foreach ($zipFilesData as $zipFileData) {
                $base_dir = ($this->data['zipPath'] ?? '') . $zipFileData['column'] . '/';
                if (!$this->zipArchive->locateName($base_dir)) {
                    $this->zipArchive->addEmptyDir($base_dir);
                }

                $path = $zipFileData['path'];

                $fileNumber++;
                $preparedFileName = $fileName = basename($path);

                if (!empty($zipFileData['fileNameTemplate'])) {
                    $templateData                  = $zipFileData['templateData'];
                    $templateData['currentNumber'] = $fileNumber;
                    $templateData['fileName']      = $fileName;
                    $templateData['entity']        = null;

                    $preparedFileName = $this->createZipFileName(
                        $fileName,
                        $zipFileData['fileNameTemplate'],
                        $templateData
                    );
                }

                try {
                    $this->zipArchive->addFile($path, $base_dir . $preparedFileName);
                } catch (\Throwable $e) {
                    $GLOBALS['log']->error('Export ZIP Error: ' . $e->getMessage());
                    $this->zipArchive->addFile($path, $base_dir . $fileName);
                }
            }
        }

        return $res;
    }

    protected function createZipFileName(string $fileName, string $fileNameTemplate, array $templateData): string
    {
        $preparedFileName = $fileName;

        $parts = explode('.', $fileName);
        $ext   = array_pop($parts);

        $newFileName = $this->getTwig()->renderTemplate($fileNameTemplate, $templateData);

        if (!empty($newFileName)) {
            $preparedFileName = $newFileName . '.' . $ext;
        }

        return $preparedFileName;
    }

    protected function createCacheFile(): array
    {
        $limit  = $this->data['limit'];
        $offset = $this->data['offset'];

        $total = $this->getTotal();

        if (empty($this->data['disableCacheChunk']) && empty($this->data['feed']['separateJob']) && $limit < $total) {
            return $this->createCacheFileByChunks($total);
        }

        $tmpDir = self::TMP_DIR . DIRECTORY_SEPARATOR . $this->data['exportJobId'] . DIRECTORY_SEPARATOR . Util::generateUniqueHash();
        Util::createDir($tmpDir);
        $fileName = Util::generateUniqueHash() . ".txt";

        $res = [
            'configuration' => [],
            'fullFileName'  => $tmpDir . DIRECTORY_SEPARATOR . $fileName,
            'count'         => 0,
        ];

        foreach ($this->data['feed']['data']['configuration'] as $rowNumber => $row) {
            $res['configuration'][$rowNumber] = $this->prepareRow($row);
        }

        $this->getMemoryStorage()->set('exportConfiguration', $res['configuration']);

        // clearing file if it needs
        file_put_contents($res['fullFileName'], '');

        $file = fopen($res['fullFileName'], 'a');

        if (!empty($total) && !empty($records = $this->getRecords($offset, $limit))) {
            $this->getMemoryStorage()->set('exportRecordsPartOffset', $offset);
            $this->getMemoryStorage()->set('exportRecordsPart', $records);
            foreach ($records as $record) {
                $rowData = [];
                foreach ($res['configuration'] as $row) {
                    $result = $this->convertor->convert($record, $row);

                    if ($row['zip'] && isset($result['__fileEntities'])) {
                        $base_dir = ($this->data['zipPath'] ?? '') . $row['column'] . '/';
                        if (!$this->zipArchive->locateName($base_dir)) {
                            $this->zipArchive->addEmptyDir($base_dir);
                        }
                        $fileNumber = 0;
                        foreach ($result['__fileEntities'] as $fileEntity) {
                            $path = $fileEntity->findOrCreateLocalFilePath($this->getZipTmpDir());
                            if (!file_exists($path)) {
                                throw new BadRequest("File '{$path}' does not exist.");
                            }
                            $fileNumber++;
                            $preparedFileName = $fileName = basename($path);

                            if (!empty($row['fileNameTemplate'])) {
                                $templateData = [
                                    'currentNumber' => $fileNumber,
                                    'fileName'      => $fileName,
                                    'entity'        => $record['_entity'] ?? null,
                                    'entityId'      => $record['id']
                                ];

                                $preparedFileName = $this->createZipFileName(
                                    $fileName,
                                    $row['fileNameTemplate'],
                                    $templateData
                                );
                            }

                            try {
                                $this->zipArchive->addFile($path, $base_dir . $preparedFileName);
                            } catch (\Throwable $e) {
                                $GLOBALS['log']->error('Export ZIP Error: ' . $e->getMessage());
                                $this->zipArchive->addFile($path, $base_dir . $fileName);
                            }
                        }
                        unset($result['__fileEntities']);
                    }

                    $rowData[] = $result;
                }

                fwrite($file, Json::encode($rowData) . PHP_EOL);
                $res['count']++;

            }
        }

        fclose($file);

        return $res;
    }


    public function getZipTmpDir(): string
    {
        return \Atro\Jobs\MassDownload::ZIP_TMP_DIR . DIRECTORY_SEPARATOR . 'export' . DIRECTORY_SEPARATOR . $this->data['exportJobId'];
    }

    protected function getDelimiter(): string
    {
        $delimiter = empty($this->data['feed']['csvFieldDelimiter']) ? ';' : $this->data['feed']['csvFieldDelimiter'];
        if ($delimiter === '\t') {
            $delimiter = "\t";
        }

        return $delimiter;
    }

    protected function getEnclosure(): string
    {
        return $this->data['feed']['csvTextQualifier'] !== 'doubleQuote' ? "'" : '"';
    }

    protected function getAttribute(string $id): Entity
    {
        return $this->getEntityManager()->getEntity('Attribute', $id);
    }

    protected function getAcl(): \Espo\Core\Acl
    {
        return $this->getContainer()->get('acl');
    }

    protected function getEntityManager(): EntityManager
    {
        return $this->getContainer()->get('entityManager');
    }

    protected function getService(string $serviceName): Record
    {
        return $this->getContainer()->get('serviceFactory')->create($serviceName);
    }

    protected function getEntityService(): Record
    {
        $service = $this->getService($this->data['feed']['entity']);

        return $service;
    }

    protected function getConfig(): Config
    {
        return $this->getContainer()->get('config');
    }

    protected function getMetadata(): Metadata
    {
        return $this->getContainer()->get('metadata');
    }

    protected function translate(string $key, string $tab, string $scope = 'Global'): string
    {
        return $this->getContainer()->get('language')->translate($key, $tab, $scope);
    }

    protected function getLanguage(string $locale): Language
    {
        return new Language($this->getContainer(), $locale);
    }

    protected function getContainer(): Container
    {
        return $this->getInjection('container');
    }

    protected function getTwig(): Twig
    {
        return $this->getContainer()->get('twig');
    }

    protected function init()
    {
        parent::init();

        $this->addDependency('container');
    }

    protected function getCollectionFromIds(mixed $entityIds): EntityCollection
    {
        $result = $this->getEntityService()->findEntities([
            "where"       => [
                [
                    "attribute" => "id",
                    "type"      => "in",
                    "value"     => $entityIds
                ]
            ],
            "withDeleted" => true
        ]);

        return $result['collection'];
    }

    protected function getWhere(): array
    {
        return !empty($this->data['feed']['data']['where']) ? $this->data['feed']['data']['where'] : [];
    }

    protected function getAmountOfAlreadyPendingChunks(string $exportJobId): int
    {
        return $this->getEntityManager()->getRepository('Job')
            ->where([
                'status'   => [
                    "Pending",
                    "Running",
                ],
                'type'     => 'ExportChunk',
                'payload*' => '%"exportJobId":"' . $exportJobId . '"%',
            ])
            ->count();
    }
}
