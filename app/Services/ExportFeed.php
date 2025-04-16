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

use Atro\Core\Exceptions;
use Atro\Core\Templates\Services\Base;
use Espo\Core\Utils\Json;
use Atro\Core\Utils\Util;
use Espo\ORM\Entity;
use Atro\Core\EventManager\Event;
use Export\Jobs\ExportJobCreator;
use Export\TemplateLoaders\AbstractTemplate;
use Export\Entities\ExportFeed as ExportFeedEntity;

class ExportFeed extends Base
{
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

        $feedEntity = $feed->get('entity') ?? $feed->getFeedField('entity');

        foreach ($fields as $field) {
            $defs = $this->getMetadata()->get("entityDefs.{$feedEntity}.fields.{$field}");

            $type = $field === 'id' ? 'varchar' : $defs['type'] ?? null;
            if (empty($type)) {
                continue;
            }

            if (!empty($defs['measureId'])) {
                $field = 'unit' . ucfirst($field);
            }

            $data = [
                'name'                      => $field,
                'type'                      => 'Field',
                'columnType'                => 'name',
                lcfirst($entityName) . 'Id' => $feed->get('id')
            ];

            if ($type === 'link') {
                $data['exportBy'] = ['id'];
            }

            $item = $this->getEntityManager()->getRepository('ExportConfiguratorItem')->get();
            $item->set($data);

            try {
                $this->getEntityManager()->saveEntity($item);
            } catch (Exceptions\NotUnique $e) {
            }

            if (!empty($this->getConfig()->get('isMultilangActive')) && !empty($defs['isMultilang']) && empty($defs['measureId'])) {
                foreach ($defs['lingualFields'] ?? [] as $languageField) {
                    $item = $this->getEntityManager()->getRepository('ExportConfiguratorItem')->get();
                    $item->set(array_merge($data, ['name' => $languageField]));
                    try {
                        $this->getEntityManager()->saveEntity($item);
                    } catch (Exceptions\NotUnique $e) {
                    }
                }
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

        $item = $this->getEntityManager()->getRepository('ExportConfiguratorItem')->get();
        $item->set([
            'type'                      => 'Fixed value',
            'columnType'                => 'custom',
            'column'                    => 'Fixed value',
            lcfirst($entityName) . 'Id' => $feed->get('id')
        ]);

        $this->getEntityManager()->saveEntity($item);

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

        $item = $this->getEntityManager()->getRepository('ExportConfiguratorItem')->get();
        $item->set([
            'type'                      => 'script',
            'columnType'                => 'custom',
            'column'                    => 'Script',
            'script'                    => '{{ configuration.type }} {{ record.id }} {{ record.name }}',
            lcfirst($entityName) . 'Id' => $feed->get('id')
        ]);
        $this->getEntityManager()->saveEntity($item);

        return true;
    }

    public function addAttributes(\stdClass $data): bool
    {
        switch ($data->entityType) {
            case 'Sheet':
                $sheet = $this->getEntityManager()->getEntity('Sheet', $data->id);
                $items = $sheet->get('configuratorItems');
                $relName = 'sheetId';
                $feed = $this->readEntity($sheet->get('exportFeedId'));
                break;
            default:
                $feed = $this->readEntity($data->id);
                $items = $feed->get('configuratorItems');
                $relName = 'exportFeedId';
        }

        $addedAttributes = [];
        if (!empty($items) && count($items) > 0) {
            foreach ($items as $item) {
                if (!empty($item->get('attributeId')) && $item->get('language') === 'main') {
                    $addedAttributes[] = $item->get('attributeId');
                }
            }
        }

        if (property_exists($data, 'ids')) {
            $params['where'] = [
                [
                    'type'      => 'equals',
                    'attribute' => 'id',
                    'value'     => $data->ids,
                ]
            ];
        }

        if (property_exists($data, 'where')) {
            $params['where'] = Json::decode(Json::encode($data->where), true);
        }

        if (!isset($params['where'])) {
            return false;
        }

        $exportConfiguratorItemService = $this->getInjection('serviceFactory')->create('ExportConfiguratorItem');

        /** @var \Pim\Repositories\Attribute $attributeRepository */
        $attributeRepository = $this->getEntityManager()->getRepository('Attribute');

        /** @var array $selectParams */
        $selectParams = $this->getSelectManager('Attribute')->getSelectParams($params, true, true);

        if ($attributeRepository->count($selectParams) > 2000) {
            throw new Exceptions\BadRequest($this->getInjection('language')->translate('toManyAttributesSelected', 'exceptions', 'ExportFeed'));
        }

        foreach ($attributeRepository->find($selectParams) as $attribute) {
            if (in_array($attribute->get('id'), $addedAttributes)) {
                continue;
            }

            $post = new \stdClass();
            $post->type = 'Attribute';
            $post->name = $attribute->get('name');
            if (empty($feed->get('language'))) {
                $post->language = 'main';
            }
            $post->$relName = $data->id;
            $post->attributeId = $attribute->get('id');

            if (!empty($feed->get('channelId'))) {
                $post->channelId = $feed->get('channelId');
                $post->channelName = $feed->get('channelName');
            }

            $post->attributeValue = 'value';

            switch ($attribute->get('type')) {
                case 'currency':
                    $post->mask = "{{value}} {{currency}}";
                    break;
                case 'float':
                case 'int':
                    if (!$attribute->get('measureId')) {
                        $post->attributeValue = 'valueNumeric';
                    }
                    break;
                case 'varchar':
                    if (!$attribute->get('measureId')) {
                        $post->attributeValue = 'valueString';
                    }
                    break;
                case 'extensibleEnum':
                case 'extensibleMultiEnum':
                    $post->exportBy = ["name"];
                    break;
            }

            $exportConfiguratorItemService->createEntity($post);
        }

        return true;
    }

    public function removeAllItems(string $entityType, string $id): bool
    {
        $this->getRepository()->removeConfiguratorItems($entityType, $id);

        return true;
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

        $entity->set('replaceAttributeValues', !empty($entity->getFeedField('replaceAttributeValues')));
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
                'language'                  => $item->get('language'),
                'fallbackLanguage'          => $item->get('fallbackLanguage'),
                'column'                    => $eciService->prepareColumnName($item),
                'template'                  => $feed->get('template'),
                'emptyValue'                => $feed->getFeedField('emptyValue'),
                'nullValue'                 => $feed->getFeedField('nullValue'),
                'markForNoRelation'         => $feed->getFeedField('markForNoRelation'),
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
            ];
            if ($feed->get('type') === 'simple') {
                $row['convertCollectionToString'] = true;
                $row['convertRelationsToString'] = true;
            }

            if ($item->get('type') === 'Field') {
                if ($item->get('name') !== 'id' && empty($this->getMetadata()->get(['entityDefs', $row['entity'], 'fields', $item->get('name')]))) {
                    throw new Exceptions\BadRequest(sprintf($this->getInjection('language')->translate('noSuchField', 'exceptions', 'ExportFeed'), $item->get('name')));
                }
                $row['field'] = $item->get('name');
            }

            if ($item->get('type') === 'Attribute') {
                $attribute = $this->getEntityManager()->getEntity('Attribute', $item->get('attributeId'));
                if (empty($attribute)) {
                    throw new Exceptions\BadRequest(sprintf($this->getInjection('language')->translate('noSuchAttribute', 'exceptions', 'ExportFeed'), $item->get('name')));
                }

                $row['replaceAttributeValues'] = !empty($feed->getFeedField('replaceAttributeValues'));
                $row['attributeId'] = $attribute->get('id');
                $row['attributeName'] = $attribute->get('name');

                $row['channelLocales'] = [];
                $row['channelId'] = $item->get('channelId');

                if (!empty($channel = $item->get('channel'))) {
                    $row['channelLocales'] = $channel->get('locales');
                }

                if (empty($row['attributeValue'])) {
                    switch ($attribute->get('type')) {
                        case 'rangeInt':
                        case 'rangeFloat':
                            $row['attributeValue'] = "valueFrom";
                            break;
                        default:
                            $row['attributeValue'] = 'value';
                    }
                }
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
            ->getInjection('eventManager')
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
        if (!property_exists($requestData, 'fileType')  || empty($scope = $requestData->scope)) {
            throw new Exceptions\BadRequest();
        }

        if (empty($requestData->exportAllField) && empty($requestData->fieldList)) {
            throw new Exceptions\BadRequest();
        }

        if(!in_array($requestData->fileType, ['csv', 'xlsx'])) {
            throw new Exceptions\BadRequest();
        }

        $baseConfiguration  =  [
            'columnType' => 'name',
            'column' => '',
            'template' => NULL,
            'emptyValue' => '',
            'nullValue' => 'Null',
            'markForNoRelation' => 'Null',
            'decimalMark' => ',',
            'fieldDelimiterForRelation' => '|',
            'convertCollectionToString' => true,
            'convertRelationsToString' => true,
            'exportIntoSeparateColumns' => false,
            'exportBy' =>  [],
            'offsetRelation' => 0,
            'limitRelation' => 20,
            'sortFieldRelation' => '',
            'sortOrderRelation' => 'ASC',
            'type' => 'Field',
            'zip' => false,
            'entity' => $scope,
            'sortOrderField' => '',
            'thousandSeparator' => null,
            'sortOrderDirection' => '',
            'field' => '',
        ];

        $configuration = [];
        foreach ($this->getMetadata()->get(['entityDefs', $scope, 'fields'], []) as $field => $fieldDefs) {
            if($fieldDefs['type'] === 'linkMultiple' || !empty($fieldDefs['exportDisabled'])){
                continue;
            }

            if(empty($requestData->exportAllField) && !in_array($field, $requestData->fieldList)) {
                continue;
            }

            $item = $baseConfiguration;
            $item['field'] = $field;
            $item['id'] = Util::generateId();
            $item['column'] = $this->getInjection('language')->translate($field, 'fields', $scope);

            if(in_array($fieldDefs['type'], ['link', 'extensibleEnum', 'extensibleMultiEnum'])) {
                $item['exportBy'] = ['name'];
            }

            if($fieldDefs['type'] == 'file') {
                $item['exportBy'] = ['downloadUrl'];
            }

            $configuration[] = (object) $item;

        }

        $data = [
            'id' => Util::generateId(),
            'feed' => [
                'id' =>'no-such-id',
                'name' => $scope . ' on '.date('Y-m-d H:i:s'),
                'limit' => 2000,
                'separateJob' => false,
                'type' => 'simple',
                'fileType' => $requestData->fileType,
                'isFileHeaderRow' => true,
                'csvFieldDelimiter' => ';',
                'csvTextQualifier' => 'doubleQuote',
                'entity' => $scope,
                'convertCollectionToString' => true,
                'delimiter' => '~',
                'emptyValue' => '',
                'nullValue' => 'Null',
                'markForNoRelation' => 'Null',
                'decimalMark' => ',',
                'thousandSeparator' => null,
                'priority' => 'Crucial',
                'data' => (object)[
                    'where' => [],
                    'whereData' => [],
                    'whereScope' => $scope,
                    'isFileHeaderRow' => true,
                    'csvFieldDelimiter' => ';',
                    'csvTextQualifier' => 'doubleQuote',
                    'entity' => $scope,
                    'convertCollectionToString' => true,
                    'delimiter' => '~',
                    'replaceAttributeValues' => true,
                    'convertRelationsToString' => true,
                    'fieldDelimiterForRelation' => '|',
                    'emptyValue' => '',
                    'nullValue' => 'Null',
                    'markForNoRelation' => 'Null',
                    'decimalMark' => ',',
                    'thousandSeparator' => NULL,
                    'exportByMaxDepth' => '1',
                    'configuration' => $configuration
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

    protected function getChannel(string $channelId): ?Entity
    {
        return $this->getEntityManager()->getEntity('Channel', $channelId);
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
}
