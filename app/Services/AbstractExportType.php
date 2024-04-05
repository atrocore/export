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

use Espo\Core\Container;
use Espo\Core\Exceptions\Error;
use Espo\Core\FilePathBuilder;
use Espo\Core\Services\Base;
use Espo\Core\Twig\Twig;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Json;
use Espo\Core\Utils\Language;
use Espo\Core\Utils\Metadata;
use Espo\Core\Utils\Util;
use Atro\Entities\File;
use Espo\ORM\Entity;
use Espo\ORM\EntityCollection;
use Espo\ORM\EntityManager;
use Espo\Services\Record;
use Export\DataConvertor\Convertor;
use Export\Entities\ExportJob;

abstract class AbstractExportType extends Base
{
    protected array $data;

    protected Convertor $convertor;

    private int $iteration = 0;
    protected $zipArchive = null;
    protected $zipAttachment = null;

    public static function getAllFieldsConfiguration(string $scope, Metadata $metadata, Language $language): array
    {
        $configuration = [['field' => 'id', 'language' => 'main', 'column' => 'ID']];

        /** @var array $allFields */
        $allFields = $metadata->get(['entityDefs', $scope, 'fields'], []);

        foreach ($allFields as $field => $data) {
            if (!empty($data['exportDisabled']) || !empty($data['disabled'])
                || in_array(
                    $data['type'], ['jsonObject', 'linkParent', 'currencyConverted', 'available-currency', 'file', 'attachmentMultiple']
                )) {
                continue 1;
            }

            $row = [
                'field'    => $field,
                'language' => 'main',
                'column'   => $language->translate($field, 'fields', $scope)
            ];

            if (!empty($data['multilangLocale'])) {
                $row['field'] = $data['multilangField'];
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
                $row['offsetRelation'] = 0;
                $row['limitRelation'] = 20;
                $row['sortFieldRelation'] = 'id';
                $row['sortOrderRelation'] = '1'; // ASC
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

    public function getCount(array $data): ?int
    {
        $this->setData($data);

        if (!empty($this->data['feed']['entity']) && $this->getAcl()->check($this->data['feed']['entity'], 'read')) {
            $result = $this->getEntityService()->findEntities($this->getSelectParams());
            if (array_key_exists('total', $result) && $result['total'] > 0) {
                return $result['total'];
            }
        }

        return null;
    }

    public function export(array $data, ExportJob $exportJob): File
    {
        $this->setData($data);
        $this->convertor = $this->getDataConvertor();

        return $this->runExport($exportJob);
    }

    abstract public function runExport(ExportJob $exportJob): File;

    protected function setData(array $data): void
    {
        $this->data = Json::decode(Json::encode($data), true);
    }

    protected function getExportFileName(string $extension): string
    {
        $fileName = preg_replace("/[^a-z0-9.!?]/", '', mb_strtolower($this->data['feed']['name']));

        if (!empty($this->data['iteration'])) {
            $fileName .= '_' . $this->data['iteration'];
        }

        $fileName .= '_' . date('YmdHis') . '.' . $extension;

        return $fileName;
    }

    protected function prepareRow(array $row): array
    {
        $feedData = $this->data['feed']['data'];

        if (!isset($row['channelId'])) {
            $row['channelId'] = null;
        }

        $row['delimiter'] = !empty($feedData['delimiter']) ? $feedData['delimiter'] : ',';
        $row['emptyValue'] = !empty($feedData['emptyValue']) ? $feedData['emptyValue'] : '';
        $row['nullValue'] = array_key_exists('nullValue', $feedData) ? $feedData['nullValue'] : 'Null';
        $row['markForNoRelation'] = !empty($feedData['markForNoRelation']) ? $feedData['markForNoRelation'] : 'Null';
        $row['decimalMark'] = !empty($feedData['decimalMark']) ? $feedData['decimalMark'] : ',';
        $row['thousandSeparator'] = !empty($feedData['thousandSeparator']) ? $feedData['thousandSeparator'] : '';
        $row['fieldDelimiterForRelation'] = !empty($feedData['fieldDelimiterForRelation']) ? $feedData['fieldDelimiterForRelation'] : '|';
        $row['entity'] = $feedData['entity'];

        $feedLanguage = $this->data['feed']['language'];
        $feedFallbackLanguage = $this->data['feed']['fallbackLanguage'];

        if(
            $row['type'] === 'Field'
            && !empty($feedLanguage)
            && $this->getMetadata()->get(['entityDefs', $row['entity'], 'fields', $row['field'],'isMultilang'], false)
        ){
            $row['language'] = $feedLanguage;
            $row['fallbackLanguage'] = $feedFallbackLanguage;
        }

        if(
            $row['type'] === 'Attribute'
            && !empty($feedLanguage)
            && $this->getEntityManager()
                ->getRepository('Attribute')
                ->get($row['attributeId'])
                ->get('isMultilang')
        ){
            $row['language'] = $feedLanguage;
            $row['fallbackLanguage'] = $feedFallbackLanguage;
        }

        if ($row['type'] === 'Field' && !empty($row['fallbackLanguage']) ) {
            if($row['fallbackLanguage'] === 'main'){
                $row['fallbackField'] = $row['field'];
            }else{
                $row['fallbackField']  = $row['field'].ucfirst(Util::toCamelCase(strtolower($row['fallbackLanguage'])));
            }
        }

        // change field name for multilingual field
        if ($row['type'] === 'Field' && $row['language'] !== 'main') {
            $row['field'] .= ucfirst(Util::toCamelCase(strtolower($row['language'])));
        }

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
            'where'       => !empty($this->data['feed']['data']['where']) ? $this->data['feed']['data']['where'] : [],
            'withDeleted' => !empty($this->data['feed']['data']['withDeleted']),
        ];

        return $params;
    }

    protected function getRecords(int $offset = 0): array
    {
        if (!empty($this->data['feed']['separateJob']) && !empty($this->iteration)) {
            return [];
        }

        if (!$this->getAcl()->check($this->data['feed']['entity'], 'read')) {
            return [];
        }

        $params = $this->getSelectParams();
        $params['disableCount'] = true;
        $params['offset'] = $offset;
        $params['maxSize'] = $this->data['limit'];
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

        /**
         * Set language prism via prism filter
         */
        if (empty($GLOBALS['languagePrism']) && !empty($params['where'])) {
            foreach ($params['where'] as $where) {
                if (!empty($where['value'][0]) && is_string($where['value'][0]) && strpos((string)$where['value'][0], 'prismVia') !== false) {
                    $language = str_replace('prismVia', '', $where['value'][0]);
                    if ($language === 'Main') {
                        $languagePrism = 'main';
                    } else {
                        $parts = explode("_", Util::toUnderScore($language));
                        $languagePrism = $parts[0] . '_' . strtoupper($parts[1]);
                    }
                    $GLOBALS['languagePrism'] = $languagePrism;
                }
            }
        }

        if (isset($GLOBALS['languagePrism'])) {
            $languagePrism = $GLOBALS['languagePrism'];
            unset($GLOBALS['languagePrism']);
        }

        $result = $this->getEntityService()->findEntities($params);

        if (isset($result['collection'])) {
            $list = [];
            foreach ($result['collection'] as $entity) {
                $list[] = array_merge($entity->toArray(), ['_entity' => $entity]);
            }
        } else {
            $list = $result['list'];
        }

        if (isset($languagePrism)) {
            $GLOBALS['languagePrism'] = $languagePrism;
        }

        $this->iteration++;

        return $list;
    }

    public function getCollection(int $offset = null): ?EntityCollection
    {
        if (!$this->getAcl()->check($this->data['feed']['entity'], 'read')) {
            return null;
        }

        if ($offset === null) {
            $offset = $this->data['offset'];
        }

        $params = $this->getSelectParams();
        $params['offset'] = $offset;
        $params['maxSize'] = $this->data['limit'];
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

        $result = $this->getEntityService()->findEntities($params);
        if (isset($result['collection']) && count($result['collection']) > 0) {
            return $result['collection'];
        }

        return null;
    }

    protected function createCacheFile(): array
    {
        // prepare full file name
        $fileName = Util::generateId() . ".txt";
        $filePath = $this->createPath();
        $fullFilePath = $this->getConfig()->get('filesPath', 'upload/files/') . $filePath;
        Util::createDir($fullFilePath);

        /**
         * Set language prism
         */
        if (!empty($this->data['feed']['language'])) {
            $GLOBALS['languagePrism'] = $this->data['feed']['language'];
        }

        $res = [
            'configuration' => [],
            'fullFileName'  => $fullFilePath . '/' . $fileName,
            'count'         => 0,
        ];

        foreach ($this->data['feed']['data']['configuration'] as $rowNumber => $row) {
            $res['configuration'][$rowNumber] = $this->prepareRow($row);
        }
        // clearing file if it needs
        file_put_contents($res['fullFileName'], '');

        $file = fopen($res['fullFileName'], 'a');

        $limit = $this->data['limit'];
        $offset = $this->data['offset'];

        while (!empty($records = $this->getRecords($offset))) {
            $this->getMemoryStorage()->set('exportRecordsPart', $records);
            $offset = $offset + $limit;
            $this->putProductAttributeValues($res['configuration'], $records);
            foreach ($records as $record) {
                $rowData = [];
                foreach ($res['configuration'] as $row) {
                    $result = $this->convertor->convert($record, $row);

                    if ($row['zip'] && isset($result['__assetPaths'])) {
                        $base_dir = ($this->data['zipPath'] ?? '') . $row['column'] . '/';
                        if (!$this->zipArchive->locateName($base_dir)) {
                            $this->zipArchive->addEmptyDir($base_dir);
                        }
                        $fileNumber = 0;
                        foreach ($result['__assetPaths'] as $path) {
                            $fileNumber++;
                            $preparedFileName = $fileName = basename($path);

                            if (!empty($row['fileNameTemplate'])) {
                                $parts = explode('.', $fileName);
                                $ext = array_pop($parts);

                                $newFileName = $this->getTwig()->renderTemplate((string)$row['fileNameTemplate'], [
                                    'currentNumber' => $fileNumber,
                                    'fileName'      => implode('.', $parts),
                                    'entity'        => $record['_entity']
                                ]);

                                if (!empty($newFileName)) {
                                    $preparedFileName = $newFileName . '.' . $ext;
                                }
                            }

                            try {
                                $this->zipArchive->addFile($path, $base_dir . $preparedFileName);
                            } catch (\Throwable $e) {
                                $GLOBALS['log']->error('Export ZIP Error: ' . $e->getMessage());
                                $this->zipArchive->addFile($path, $base_dir . $fileName);
                            }
                        }
                        unset($result['__assetPaths']);
                    }

                    $rowData[] = $result;
                }

                fwrite($file, Json::encode($rowData) . PHP_EOL);
                $res['count']++;
            }
            $this->convertor->clearMemoryOfLoadedEntities();
        }

        fclose($file);

        return $res;
    }

    protected function putProductAttributeValues(array $configuration, array &$records): void
    {
        $attributesIds = [];
        foreach ($configuration as $row) {
            if (!empty($row['attributeId']) && $row['entity'] === 'Product') {
                $attributesIds[] = $row['attributeId'];
            }
        }

        if (!empty($attributesIds)) {
            // load attributes to memory
            if (empty($this->getMemoryStorage()->get('attributesLoaded'))) {
                $attributeRepository = $this->getEntityManager()->getRepository('Attribute');
                $attributes = $attributeRepository->where(['id' => $attributesIds])->find();
                foreach ($attributes as $attribute) {
                    $attributeRepository->putToCache($attribute->get('id'), $attribute);
                }
                $this->getMemoryStorage()->set('attributesLoaded', true);
            }

            $pavWhere = [
                [
                    'type'      => 'in',
                    'attribute' => 'productId',
                    'value'     => array_column($records, 'id')
                ],
                [
                    'type'      => 'in',
                    'attribute' => 'attributeId',
                    'value'     => $attributesIds
                ]
            ];

            $res = $this
                ->getService('ProductAttributeValue')
                ->findEntities([
                    'where'        => $pavWhere,
                    'disableCount' => true
                ]);

            $pavRepo = $this->getEntityManager()->getRepository('ProductAttributeValue');

            $pavCollectionKeys = [];
            $attributesKeys = [];
            foreach ($res['collection'] as $pav) {
                $itemKey = $pavRepo->getCacheKey($pav->get('id'));
                $this->getMemoryStorage()->set($itemKey, $pav);
                $pavCollectionKeys[implode('_', [$pav->get('productId'), $pav->get('attributeId'), $pav->get('language'), $pav->get('channelId')])] = $itemKey;
                $attributesKeys[$pav->get('attributeId')][] = $itemKey;
            }
            $this->getMemoryStorage()->set('pavCollectionKeys', $pavCollectionKeys);
            $this->getMemoryStorage()->set('attributesKeys', $attributesKeys);
        }
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

    protected function getSelectManager(string $name): \Espo\Core\SelectManagers\Base
    {
        return $this->getContainer()->get('selectManagerFactory')->create($name);
    }

    protected function getLanguage(string $locale): Language
    {
        return new Language($this->getContainer(), $locale);
    }

    protected function createPath(): string
    {
        return $this->getContainer()->get('filePathBuilder')->createPath(FilePathBuilder::UPLOAD);
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
}
