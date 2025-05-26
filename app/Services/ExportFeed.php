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

use Atro\Core\AttributeFieldConverter;
use Atro\Core\Container;
use Atro\Core\EventManager\Manager;
use Atro\Core\Exceptions;
use Atro\Core\Templates\Services\Base;
use Atro\Core\Utils\Language;
use Doctrine\DBAL\ParameterType;
use Espo\Core\Utils\Json;
use Atro\Core\Utils\Util;
use Espo\ORM\Entity;
use Atro\Core\EventManager\Event;
use Export\Jobs\ExportJobCreator;
use Export\TemplateLoaders\AbstractTemplate;
use Export\Entities\ExportFeed as ExportFeedEntity;

class ExportFeed extends Base
{
    protected static array $languages = [];

    public function runExport(string $feedId, string $payload = null, ?string $priority = null): bool
    {
        $exportFeed = $this->getEntity($feedId);
        if (empty($exportFeed)) {
            throw new Exceptions\NotFound();
        }

        if (empty($exportFeed->get('isActive'))) {
            return false;
        }

        $data = [
            'id'   => Util::generateId(),
            'feed' => $this->prepareFeedData($exportFeed)
        ];

        if (!empty($payload)) {
            $payload = @json_decode($payload, true);
            if (!empty($payload)) {
                foreach ($payload as $key => $value) {
                    $data['feed']['data']->{$key} = $value;
                }
            }
            $data['executeNow'] = !empty($payload['executeNow']);
        }

        if (!empty($priority)) {
            $data['feed']['priority'] = $priority;
        }

        return $this->pushExport($data);
    }

    public function exportFile(\stdClass $requestData): bool
    {
        if (!property_exists($requestData, 'id')) {
            throw new Exceptions\NotFound();
        }

        $exportFeed = $this->getEntity($requestData->id);
        if (empty($exportFeed)) {
            throw new Exceptions\NotFound();
        }

        if (empty($exportFeed->get('isActive'))) {
            return false;
        }

        switch ($exportFeed->get('fileType')) {
            case 'csv':
                $configuratorItems = $exportFeed->get('configuratorItems');
                if (empty($configuratorItems[0])) {
                    throw new Exceptions\BadRequest($this->getInjection('language')->translate('noConfiguratorItems', 'exceptions', 'ExportFeed'));
                }
                break;
            case 'xlsx':
                if (!empty($exportFeed->get('hasMultipleSheets'))) {
                    if (!empty($sheets = $exportFeed->get('sheets'))) {
                        foreach ($sheets as $sheet) {
                            if (!empty($sheet->get('isActive'))) {
                                break 2;
                            }
                        }
                    }
                    throw new Exceptions\BadRequest($this->getInjection('language')->translate('noSheets', 'exceptions', 'ExportFeed'));
                } else {
                    $configuratorItems = $exportFeed->get('configuratorItems');
                    if (empty($configuratorItems[0])) {
                        throw new Exceptions\BadRequest($this->getInjection('language')->translate('noConfiguratorItems', 'exceptions', 'ExportFeed'));
                    }
                }
                break;
        }

        $this->getRepository()->removeInvalidConfiguratorItems($exportFeed->get('id'));

        $data = [
            'id'   => Util::generateId(),
            'feed' => $this->prepareFeedData($exportFeed)
        ];

        if (!empty($requestData->ignoreFilter)) {
            $data['feed']['data']->where = [];
        }

        if (!empty($requestData->entityFilterData)) {
            if (!empty($requestData->entityFilterData->byWhere)) {
                $data['feed']['data']->where = array_merge($data['feed']['data']->where, $requestData->entityFilterData->where);
            } else {
                $data['feed']['data']->where[] = [
                    'type'      => 'in',
                    'attribute' => 'id',
                    'value'     => $requestData->entityFilterData->ids
                ];
            }
        }

        return $this->pushExport($data);
    }

    public function addFields(string $entityName, string $id, array $fields): bool
    {
        if (!in_array($entityName, ['ExportFeed', 'Sheet'])) {
            throw new Exceptions\BadRequest('Wrong entity name');
        }

        $feed = $this->getEntityManager()->getRepository($entityName)->get($id);
        if (empty($feed)) {
            return false;
        }

        $exportFeed = $entityName === 'ExportFeed' ? $feed : $feed->get('exportFeed');

        $languageObj = self::getLocalizedLanguage($this->getInjection('container'), $exportFeed->get('localeId'));

        $feedEntity = $feed->get('entity') ?? $feed->getFeedField('entity');

        foreach ($fields as $field) {
            $defs = $this->getMetadata()->get("entityDefs.{$feedEntity}.fields.{$field}");

            $type = $field === 'id' ? 'varchar' : $defs['type'] ?? null;
            if (empty($type)) {
                continue;
            }

            $data = [
                'name'                      => $field,
                'type'                      => 'Field',
                'columnType'                => 'name',
                lcfirst($entityName) . 'Id' => $feed->get('id')
            ];

            if (in_array($type, ['link', 'file'])) {
                $data['exportBy'] = ['id'];
            }

            if (in_array($type, ['rangeInt', 'rangeFloat'])) {
                $this->createExportConfiguratorItem(array_merge($data, [
                    'name'       => null,
                    'type'       => 'script',
                    'columnType' => 'custom',
                    'column'     => $languageObj->translate($field, 'fields', $feedEntity),
                    'script'     => "{{ record.{$field}From }} - {{ record.{$field}To }} {{ record.{$field}UnitName }}"
                ]));
                $this->createExportConfiguratorItem(array_merge($data, ['name' => $field . 'From']));
                $this->createExportConfiguratorItem(array_merge($data, ['name' => $field . 'To']));
            } else {
                $this->createExportConfiguratorItem($data);
            }

            if (!empty($this->getConfig()->get('isMultilangActive')) && !empty($defs['isMultilang']) && empty($defs['measureId'])) {
                foreach ($defs['lingualFields'] ?? [] as $languageField) {
                    $this->createExportConfiguratorItem(array_merge($data, ['name' => $languageField]));
                }
            }

            if (!empty($defs['measureId'])) {
                $this->createExportConfiguratorItem(array_merge($data, ['name' => $field . 'Unit']));
                $this->createExportConfiguratorItem(array_merge($data, [
                    'name'       => null,
                    'type'       => 'script',
                    'columnType' => 'custom',
                    'column'     => $languageObj->translate('unit' . ucfirst($field), 'fields', $feedEntity),
                    'script'     => "{{ record.{$field} }} {{ record.{$field}UnitName }}"
                ]));
            }
        }

        return true;
    }

    public function addAttributes(string $entityName, string $id, array $attributesIds): bool
    {
        if (!in_array($entityName, ['ExportFeed', 'Sheet'])) {
            throw new Exceptions\BadRequest('Wrong entity name');
        }

        $feed = $this->getEntityManager()->getRepository($entityName)->get($id);
        if (empty($feed)) {
            return false;
        }

        $feedEntity = $this->getEntityManager()->getRepository($feed->get('entity') ?? $feed->getFeedField('entity'))->get();

        foreach ($this->getAttributeFieldConverter()->getAttributesRowsByIds($attributesIds) as $attribute) {
            $attributesDefs = [];
            $this->getAttributeFieldConverter()->convert($feedEntity, $attribute, $attributesDefs);
            foreach ($attributesDefs as $field => $fieldDefs) {
                $data = [
                    'name'                      => $field,
                    'type'                      => 'Field',
                    'columnType'                => 'name',
                    'entityAttributeId'         => $attribute['id'],
                    lcfirst($entityName) . 'Id' => $feed->get('id')
                ];

                if (in_array($fieldDefs['type'], ['link', 'file'])) {
                    $data['exportBy'] = ['id'];
                }

                if (in_array($fieldDefs['type'], ['rangeInt', 'rangeFloat'])) {
                    $this->createExportConfiguratorItem(array_merge($data, [
                        'name'       => null,
                        'type'       => 'script',
                        'columnType' => 'custom',
                        'column'     => $fieldDefs['label'],
                        'script'     => "{{ record.{$field}From }} - {{ record.{$field}To }} {{ record.{$field}UnitName }}"
                    ]));

                    continue;
                }

                if (in_array($fieldDefs['type'], ['int', 'float', 'varchar']) && !empty($fieldDefs['measureId'])) {
                    $this->createExportConfiguratorItem(array_merge($data, [
                        'name'       => null,
                        'type'       => 'script',
                        'columnType' => 'custom',
                        'column'     => $fieldDefs['detailViewLabel'],
                        'script'     => "{{ record.{$field} }} {{ record.{$field}UnitName }}"
                    ]));
                }

                $this->createExportConfiguratorItem($data);
            }
        }

        return true;
    }

    public function addFixed(string $entityName, string $id): bool
    {
        if (!in_array($entityName, ['ExportFeed', 'Sheet'])) {
            throw new Exceptions\BadRequest('Wrong entity name');
        }

        $feed = $this->getEntityManager()->getRepository($entityName)->get($id);
        if (empty($feed)) {
            return false;
        }

        $this->createExportConfiguratorItem([
            'type'                      => 'Fixed value',
            'columnType'                => 'custom',
            'column'                    => 'Fixed value',
            lcfirst($entityName) . 'Id' => $feed->get('id')
        ]);

        return true;
    }

    public function addScript(string $entityName, string $id): bool
    {
        if (!in_array($entityName, ['ExportFeed', 'Sheet'])) {
            throw new Exceptions\BadRequest('Wrong entity name');
        }

        $feed = $this->getEntityManager()->getRepository($entityName)->get($id);
        if (empty($feed)) {
            return false;
        }

        $this->createExportConfiguratorItem([
            'type'                      => 'script',
            'columnType'                => 'custom',
            'column'                    => 'Script',
            'script'                    => '{{ configuration.type }} {{ record.id }} {{ record.name }}',
            lcfirst($entityName) . 'Id' => $feed->get('id')
        ]);

        return true;
    }

    public function removeAllItems(string $entityType, string $id): bool
    {
        $this->getRepository()->removeConfiguratorItems($entityType, $id);

        return true;
    }

    protected function createExportConfiguratorItem(array $data): void
    {
        $item = $this->getEntityManager()->getRepository('ExportConfiguratorItem')->get();
        $item->set($data);
        try {
            $this->getEntityManager()->saveEntity($item);
        } catch (Exceptions\NotUnique $e) {
        }
    }

    public function readEntity($id)
    {
        $this->getRepository()->removeInvalidConfiguratorItems($id);

        return parent::readEntity($id);
    }

    public function findLinkedEntities($id, $link, $params)
    {
        if ($link === 'configuratorItems' && !empty($exportFeed = $this->getEntity($id))) {
            $this->getRepository()->removeInvalidConfiguratorItems($exportFeed->get('id'));
            $this->putAttributesToMetadata($id);
        }

        return parent::findLinkedEntities($id, $link, $params);
    }

    public function prepareEntityForOutput(Entity $entity)
    {
        parent::prepareEntityForOutput($entity);

        foreach ($entity->getFeedFields() as $name => $value) {
            if (!in_array($name, ['fileType'])) {
                $entity->set($name, $value);
            }
        }

        if ($entity->get('type') === 'simple') {
            $entity->set('convertCollectionToString', true);
            $entity->set('convertRelationsToString', true);
        }

        if (!empty($entity->get('localeId'))) {
            $locale = $this->getEntityManager()->getEntity('Locale', $entity->get('localeId'));
            if (!empty($locale)) {
                $entity->set('localeName', $locale->get('name'));
            }
        }
    }

    public function getExportTypeService(string $type, array $data = null): AbstractExportType
    {
        $res = $this->getInjection('serviceFactory')->create('ExportType' . ucfirst($type));
        if ($data) {
            $res->setData($data);
        }

        return $res;
    }

    protected function init()
    {
        parent::init();

        $this->addDependency('container');
        $this->addDependency('serviceFactory');
        $this->addDependency('language');
        $this->addDependency('user');
    }

    protected function beforeUpdateEntity(Entity $entity, $data)
    {
        parent::beforeUpdateEntity($entity, $data);

        foreach ($entity->getFeedFields() as $name => $value) {
            if (!$entity->has($name)) {
                $entity->set($name, $value);
            }
        }
    }

    public function prepareFeedDataConfiguration(Entity $sheet): array
    {
        $items = $this->getInjection('serviceFactory')->create($sheet->getEntityType())
            ->findLinkedEntities($sheet->get('id'), 'configuratorItems', ['maxSize' => \PHP_INT_MAX, 'sortBy' => 'sortOrder']);
        if (empty($items['total'])) {
            return [];
        }

        if ($sheet->getEntityType() === 'ExportFeed') {
            /** @var ExportFeedEntity $feed */
            $feed = $sheet;
            $entityName = $sheet->getFeedField('entity');
        } else {
            /** @var ExportFeedEntity $feed */
            $feed = $sheet->get('exportFeed');
            $entityName = $sheet->get('entity');
        }

        $configuration = [];

        /** @var \Export\Services\ExportConfiguratorItem $eciService */
        $eciService = $this->getInjection('serviceFactory')->create('ExportConfiguratorItem');

        foreach ($items['collection'] as $item) {
            $row = [
                'id'                        => $item->get('id'),
                'columnType'                => $item->get('columnType'),
                'column'                    => $eciService->prepareColumnName($item),
                'template'                  => $feed->get('template'),
                'emptyValue'                => $feed->getFeedField('emptyValue'),
                'nullValue'                 => $feed->getFeedField('nullValue'),
                'markForNoRelation'         => $feed->getFeedField('markForNoRelation'),
                'markForUnlinkedAttribute'  => $feed->getFeedField('markForUnlinkedAttribute'),
                'thousandSeparator'         => $feed->getThousandSeparator(),
                'decimalMark'               => $feed->getDecimalMark(),
                'fieldDelimiterForRelation' => $feed->getFeedField('fieldDelimiterForRelation'),
                'convertCollectionToString' => !empty($feed->getFeedField('convertCollectionToString')),
                'convertRelationsToString'  => !empty($feed->getFeedField('convertRelationsToString')),
                'exportIntoSeparateColumns' => $item->get('exportIntoSeparateColumns'),
                'exportBy'                  => $item->get('exportBy'),
                'mask'                      => $item->get('mask'),
                'searchFilter'              => $item->get('searchFilter'),
                'filterField'               => $item->get('filterField'),
                'filterFieldValue'          => $item->get('filterFieldValue'),
                'offsetRelation'            => $item->get('offsetRelation'),
                'limitRelation'             => $item->get('limitRelation'),
                'sortFieldRelation'         => $item->get('sortFieldRelation'),
                'sortOrderRelation'         => $item->get('sortOrderRelation'),
                'type'                      => $item->get('type'),
                'fixedValue'                => $item->get('fixedValue'),
                'zip'                       => !empty($item->get('zip')),
                'fileNameTemplate'          => $item->get('fileNameTemplate'),
                'attributeValue'            => $item->get('attributeValue'),
                'entity'                    => $entityName,
                'sortOrderField'            => $sheet->get('sortOrderField'),
                'sortOrderDirection'        => $sheet->get('sortOrderDirection'),
                'script'                    => $item->get('script') ?? null,
                'entityAttributeId'         => $item->get('entityAttributeId') ?? null,
            ];
            if ($feed->get('type') === 'simple') {
                $row['convertCollectionToString'] = true;
                $row['convertRelationsToString'] = true;
            }

            if ($item->get('type') === 'Field') {
                $row['field'] = $item->get('name');
            }

            $configuration[] = $row;
        }

        return $configuration;
    }

    public function prepareFeedData(ExportFeedEntity $feed): array
    {
        $result = $feed->toArray();
        foreach ($feed->getFeedFields() as $name => $value) {
            $result[$name] = $value;
            $result['data']->$name = $value;
        }
        $result['decimalMark'] = $result['data']->decimalMark = $feed->getDecimalMark();
        $result['thousandSeparator'] = $result['data']->thousandSeparator = $feed->getThousandSeparator();

        $result['fileType'] = $feed->get('fileType');

        if (!empty($feed->get('hasMultipleSheets'))) {
            $sheets = $this->findLinkedEntities($feed->get('id'), 'sheets', ['maxSize' => \PHP_INT_MAX, 'sortBy' => 'sortOrder']);
            foreach ($sheets['collection'] as $sheet) {
                if (empty($sheet->get('isActive'))) {
                    continue;
                }
                $result['sheets'][] = [
                    'name'               => $sheet->get('name'),
                    'entity'             => $sheet->get('entity'),
                    'sortOrderField'     => $sheet->get('sortOrderField'),
                    'sortOrderDirection' => $sheet->get('sortOrderDirection'),
                    'data'               => $sheet->get('data'),
                    'configuration'      => $this->prepareFeedDataConfiguration($sheet)
                ];
            }
        } else {
            $result['data']->configuration = Json::decode(Json::encode($this->prepareFeedDataConfiguration($feed)));
        }

        return $this
            ->getEventManager()
            ->dispatch('ExportFeedService', 'prepareFeedData', new Event(['feed' => $feed, 'result' => $result]))
            ->getArgument('result');
    }

    public function pushExport(array $data): bool
    {
        $name = $this->getInjection('language')->translate('createExportJobs', 'additionalTranslates', 'ExportFeed');
        $name = sprintf($name, $data['feed']['name']);

        $priority = empty($data['feed']['priority']) ? 'Normal' : (string)$data['feed']['priority'];

        $this->getRepository()->updateLastTime($data['feed']['id'], new \DateTime());

        if (!empty($data['executeNow'])) {
            $data['ownerUserId'] = $this->getUser()->get('id');
            $data['priority'] = AbstractExportType::PRIORITIES[$priority];
            $this->getInjection('container')->get(ExportJobCreator::class)->runNow($data);
        } else {
            $jobEntity = $this->getEntityManager()->getEntity('Job');
            $jobEntity->set([
                'name'        => $name,
                'type'        => 'ExportJobCreator',
                'payload'     => $data,
                'priority'    => AbstractExportType::PRIORITIES[$priority],
                'ownerUserId' => $this->getUser()->get('id')
            ]);
            $this->getEntityManager()->saveEntity($jobEntity);
        }

        return true;
    }

    /**
     * @param string $templateName
     *
     * @return string|null
     */
    public function getOriginTemplate(string $template): ?string
    {
        if (!empty($className = $this->getMetadata()->get(['app', 'templateLoaders', $template]))) {
            if (is_a($className, AbstractTemplate::class, true)) {
                $templateClass = $this->getInjection('container')->get($className);

                return $templateClass->loadTemplateFromFile();
            }
        }

        return null;
    }

    public function getAvailableTemplates(array $data): array
    {
        $result = [];

        foreach ($this->getMetadata()->get(['app', 'templateLoaders'], []) as $name => $className) {
            if (is_a($className, AbstractTemplate::class, true)) {
                $templateClass = $this->getInjection('container')->get($className);

                if ($templateClass->isTemplateCompatible($data)) {
                    $result[$name] = $templateClass->getName();
                }
            }
        }

        return $result;
    }

    public function directExportFile(\stdClass $requestData): bool
    {
        if (!property_exists($requestData, 'fileType') || empty($scope = $requestData->scope)) {
            throw new Exceptions\BadRequest();
        }

        if (empty($requestData->exportAllField) && empty($requestData->fieldList)) {
            throw new Exceptions\BadRequest();
        }

        if (!in_array($requestData->fileType, ['csv', 'xlsx'])) {
            throw new Exceptions\BadRequest();
        }

        $baseConfiguration = [
            'columnType'                => 'name',
            'column'                    => '',
            'template'                  => NULL,
            'emptyValue'                => '',
            'nullValue'                 => 'Null',
            'markForNoRelation'         => 'Null',
            'decimalMark'               => ',',
            'fieldDelimiterForRelation' => '|',
            'convertCollectionToString' => true,
            'convertRelationsToString'  => true,
            'exportIntoSeparateColumns' => false,
            'exportBy'                  => [],
            'offsetRelation'            => 0,
            'limitRelation'             => 20,
            'sortFieldRelation'         => '',
            'sortOrderRelation'         => 'ASC',
            'type'                      => 'Field',
            'zip'                       => false,
            'entity'                    => $scope,
            'sortOrderField'            => '',
            'thousandSeparator'         => null,
            'sortOrderDirection'        => '',
            'field'                     => '',
        ];

        $configuration = [];
        foreach ($this->getMetadata()->get(['entityDefs', $scope, 'fields'], []) as $field => $fieldDefs) {
            if ($fieldDefs['type'] === 'linkMultiple' || !empty($fieldDefs['exportDisabled'])) {
                continue;
            }

            if (empty($requestData->exportAllField) && !in_array($field, $requestData->fieldList)) {
                continue;
            }

            $item = $baseConfiguration;
            $item['field'] = $field;
            $item['id'] = Util::generateId();
            $item['column'] = $this->getInjection('language')->translate($field, 'fields', $scope);

            if (in_array($fieldDefs['type'], ['link', 'extensibleEnum', 'extensibleMultiEnum'])) {
                $item['exportBy'] = ['name'];
            }

            if ($fieldDefs['type'] == 'file') {
                $item['exportBy'] = ['downloadUrl'];
            }

            $configuration[] = (object)$item;

        }

        $data = [
            'id'   => Util::generateId(),
            'feed' => [
                'id'                        => 'no-such-id',
                'name'                      => $scope . ' on ' . date('Y-m-d H:i:s'),
                'limit'                     => 2000,
                'separateJob'               => false,
                'type'                      => 'simple',
                'fileType'                  => $requestData->fileType,
                'isFileHeaderRow'           => true,
                'csvFieldDelimiter'         => ';',
                'csvTextQualifier'          => 'doubleQuote',
                'entity'                    => $scope,
                'convertCollectionToString' => true,
                'delimiter'                 => '~',
                'emptyValue'                => '',
                'nullValue'                 => 'Null',
                'markForNoRelation'         => 'Null',
                'decimalMark'               => ',',
                'thousandSeparator'         => null,
                'priority'                  => 'Crucial',
                'data'                      => (object)[
                    'where'                     => [],
                    'whereData'                 => [],
                    'whereScope'                => $scope,
                    'isFileHeaderRow'           => true,
                    'csvFieldDelimiter'         => ';',
                    'csvTextQualifier'          => 'doubleQuote',
                    'entity'                    => $scope,
                    'convertCollectionToString' => true,
                    'delimiter'                 => '~',
                    'convertRelationsToString'  => true,
                    'fieldDelimiterForRelation' => '|',
                    'emptyValue'                => '',
                    'nullValue'                 => 'Null',
                    'markForNoRelation'         => 'Null',
                    'decimalMark'               => ',',
                    'thousandSeparator'         => NULL,
                    'exportByMaxDepth'          => '1',
                    'configuration'             => $configuration
                ]
            ]
        ];


        if (!empty($requestData->entityFilterData)) {
            if (!empty($requestData->entityFilterData->byWhere)) {
                $data['feed']['data']->where = array_merge($data['feed']['data']->where, $requestData->entityFilterData->where);
            } else {
                $data['feed']['data']->where[] = [
                    'type'      => 'in',
                    'attribute' => 'id',
                    'value'     => $requestData->entityFilterData->ids
                ];
            }
        }

        return $this->pushExport($data);
    }


    protected function isEntityUpdated(Entity $entity, \stdClass $data): bool
    {
        return true;
    }

    protected function getFieldsThatConflict(Entity $entity, \stdClass $data): array
    {
        return [];
    }

    public function duplicateSheets(Entity $entity, Entity $duplicatingEntity): void
    {
        if (empty($items = $duplicatingEntity->get('sheets')) || count($items) === 0) {
            return;
        }

        foreach ($items as $item) {
            $data = $item->toArray();
            $data['_duplicatingEntityId'] = $item->get('id');
            $data['exportFeedId'] = $entity->get('id');

            unset($data['id']);
            unset($data['createdAt']);
            unset($data['modifiedAt']);
            unset($data['createdById']);
            unset($data['modifiedById']);

            $this->getServiceFactory()->create('Sheet')->createEntity((object)$data);
        }
    }

    public function verifyCodeEasyCatalog(string $code)
    {
        $exportFeed = $this->getRepository()->where(['code' => $code])->findOne();
        if (empty($exportFeed)) {
            return 'Export Feed code is invalid';
        }

        $hasIdColumn = false;
        foreach ($exportFeed->configuratorItems as $configuratorItem) {
            if ($configuratorItem->get('column') == 'ID') {
                $hasIdColumn = true;
                break;
            }
        }

        if (!$hasIdColumn) {
            return 'This export feed has no ID column';
        }

        return 'Export feed is correctly configured';
    }

    public function getEasyCatalog($exportFeedCode, $offset)
    {
        $exportFeed = $this->getRepository()->where(['code' => $exportFeedCode])->findOne();
        if (empty($exportFeed)) {
            throw new Exceptions\NotFound();
        }
        $data = [
            'id'   => Util::generateId(),
            'feed' => $this->prepareFeedData($exportFeed)
        ];

        $data['offset'] = !empty($offset) ? (int)$offset : 0;
        $data['limit'] = empty($data['feed']['limit']) ? \PHP_INT_MAX : $data['feed']['limit'];

        $exportService = $this->getExportTypeService($data['feed']['type']);

        return [
            "total"      => $exportService->getCount($data),
            "urlColumns" => $exportService->getUrlColumns(),
            "records"    => $exportService->exportEasyCatalogJson(),
        ];
    }

    /**
     * Put attributes to metadata as fields
     *
     * @param string $exportFeedId
     * @return void
     * @throws Exceptions\Error
     */
    public function putAttributesToMetadata(string $exportFeedId): void
    {
        $exportFeed = $this->getEntityManager()->getEntity('ExportFeed', $exportFeedId);
        if (empty($exportFeed)) {
            return;
        }

        $languageObj = self::getLocalizedLanguage($this->getInjection('container'), $exportFeed->get('localeId'));

        $currentLocaleId = $this->getUser()->get('localeId');
        $this->getUser()->set('localeId', $exportFeed->get('localeId'));

        $conn = $this->getEntityManager()->getConnection();

        if (!empty($exportFeed->get('hasMultipleSheets'))) {
            $res = $conn->createQueryBuilder()
                ->select('a.*, s.entity as sheet_entity, c.name as channel_name')
                ->from($conn->quoteIdentifier('attribute'), 'a')
                ->leftJoin('a', 'channel', 'c', 'c.id=a.channel_id AND c.deleted=:false')
                ->innerJoin('a', 'export_configurator_item', 'i', 'i.entity_attribute_id=a.id AND i.deleted=:false')
                ->innerJoin('i', $conn->quoteIdentifier('sheet'), 's', 'i.sheet_id=s.id AND s.deleted=:false')
                ->innerJoin('s', 'export_feed', 'e', 's.export_feed_id=e.id AND e.deleted=:false')
                ->where('a.deleted=:false')
                ->andWhere('e.id=:exportFeedId')
                ->setParameter('false', false, ParameterType::BOOLEAN)
                ->setParameter('exportFeedId', $exportFeedId)
                ->fetchAllAssociative();

            $result = [];
            foreach ($res as $v) {
                $result[$v['sheet_entity']][$v['id']] = $v;
            }

            foreach ($result as $entityName => $attributes) {
                if ($this->getMetadata()->get("scopes.$entityName.hasAttribute")) {
                    $exportEntity = $this->getEntityManager()->getEntity($entityName);

                    $attributesDefs = [];
                    foreach ($attributes as $row) {
                        if (!empty($row['channel_name'])) {
                            $row['name'] = $row['name'] . ' / ' . $row['channel_name'];
                        }
                        $this->getAttributeFieldConverter()->convert($exportEntity, $row, $attributesDefs);
                    }

                    foreach ($attributesDefs as $name => $attributeDefs) {
                        $this
                            ->getMetadata()
                            ->set('entityDefs', $entityName, ['fields' => [$name => $attributeDefs]]);

                        $languageObj->set($entityName, 'fields', $name, $attributeDefs['label']);
                    }
                }
            }
        } else {
            $entityName = $exportFeed->getFeedField('entity');
            if ($this->getMetadata()->get("scopes.$entityName.hasAttribute")) {
                $attributes = $conn->createQueryBuilder()
                    ->select('a.*, c.name as channel_name')
                    ->distinct()
                    ->from($conn->quoteIdentifier('attribute'), 'a')
                    ->leftJoin('a', 'channel', 'c', 'c.id=a.channel_id AND c.deleted=:false')
                    ->innerJoin('a', 'export_configurator_item', 'i', 'i.entity_attribute_id=a.id AND i.deleted=:false')
                    ->innerJoin('i', 'export_feed', 'e', 'i.export_feed_id=e.id AND e.deleted=:false')
                    ->where('a.deleted=:false')
                    ->andWhere('e.id=:exportFeedId')
                    ->setParameter('false', false, ParameterType::BOOLEAN)
                    ->setParameter('exportFeedId', $exportFeedId)
                    ->fetchAllAssociative();

                $exportEntity = $this->getEntityManager()->getEntity($entityName);

                $attributesDefs = [];
                foreach ($attributes as $row) {
                    if (!empty($row['channel_name'])) {
                        $row['name'] = $row['name'] . ' / ' . $row['channel_name'];
                    }
                    $this->getAttributeFieldConverter()->convert($exportEntity, $row, $attributesDefs);
                }

                foreach ($attributesDefs as $name => $attributeDefs) {
                    $this
                        ->getMetadata()
                        ->set('entityDefs', $entityName, ['fields' => [$name => $attributeDefs]]);

                    $languageObj->set($entityName, 'fields', $name, $attributeDefs['label']);
                }
            }
        }
        $this->getUser()->set('localeId', $currentLocaleId);
    }

    protected function getAttributeFieldConverter(): AttributeFieldConverter
    {
        return $this->getInjection('container')->get(AttributeFieldConverter::class);
    }

    protected function getEventManager(): Manager
    {
        return $this->getInjection('eventManager');
    }

    protected function getLanguage(): Language
    {
        return $this->getInjection('language');
    }

    public static function getLocalizedLanguage(Container $container, $locale)
    {
        if (!isset(self::$languages[$locale])) {
            self::$languages[$locale] = new Language($container, $locale);
        }

        return self::$languages[$locale];
    }
}
