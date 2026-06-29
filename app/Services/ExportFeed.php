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
use Atro\Core\Utils\IdGenerator;
use Atro\Core\Utils\Language;
use Espo\Core\Utils\Json;
use Atro\Core\Utils\Util;
use Espo\ORM\Entity;
use Atro\Core\EventManager\Event;
use Espo\ORM\EntityCollection;
use Espo\ORM\IEntity;
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

        $decodedPayload    = null;
        $contentLanguageId = null;
        if (!empty($payload)) {
            $decodedPayload = @json_decode($payload, true);
            if (!empty($decodedPayload)) {
                $contentLanguageId = $decodedPayload['contentLanguageId'] ?? null;
            }
        }

        $data = [
            'id'   => IdGenerator::sortableId(),
            'feed' => $this->prepareFeedData($exportFeed, $contentLanguageId)
        ];

        if (!empty($decodedPayload)) {
            foreach ($decodedPayload as $key => $value) {
                $data['feed']['data']->{$key} = $value;
            }
            $data['executeNow'] = !empty($decodedPayload['executeNow']);
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
                    throw new Exceptions\BadRequest($this->getLanguage()->translate('noConfiguratorItems', 'exceptions', 'ExportFeed'));
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
                    throw new Exceptions\BadRequest($this->getLanguage()->translate('noSheets', 'exceptions', 'ExportFeed'));
                } else {
                    $configuratorItems = $exportFeed->get('configuratorItems');
                    if (empty($configuratorItems[0])) {
                        throw new Exceptions\BadRequest($this->getLanguage()->translate('noConfiguratorItems', 'exceptions', 'ExportFeed'));
                    }
                }
                break;
        }

        $this->getRepository()->fixLocaleIfNecessary($exportFeed->get('id'));

        if ($this->hasInvalidConfiguratorItems($exportFeed)) {
            throw new Exceptions\BadRequest($this->getLanguage()->translate('invalidConfiguratorItems', 'exceptions', 'ExportFeed'));
        }

        $contentLanguageId = $requestData->contentLanguageId ?? null;

        $data = [
            'id'   => IdGenerator::sortableId(),
            'feed' => $this->prepareFeedData($exportFeed, $contentLanguageId)
        ];

        if (!empty($requestData->ignoreFilter)) {
            $data['feed']['data']->where     = [];
            $data['feed']['data']->whereData = null;
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
        if (!$this->getAcl()->check('ExportFeed', 'edit')) {
            throw new Exceptions\Forbidden();
        }

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

            if (in_array($type, ['link', 'file', 'linkMultiple', 'measure'])) {
                $data['exportBy'] = ['id'];
            }

            if (in_array($type, ['rangeInt', 'rangeFloat'])) {
                $this->createExportConfiguratorItem(array_merge($data, [
                    'name'       => null,
                    'type'       => 'script',
                    'columnType' => 'custom',
                    'column'     => $languageObj->translate($field, 'fields', $feedEntity),
                    'script'     => "{{ record['{$field}From'] }} - {{ record['{$field}To'] }} {{ record['{$field}UnitName'] }}"
                ]));
                $this->createExportConfiguratorItem(array_merge($data, ['name' => $field . 'From']));
                $this->createExportConfiguratorItem(array_merge($data, ['name' => $field . 'To']));
                if (!empty($defs['measureId'])) {
                    $this->createExportConfiguratorItem(array_merge($data, ['name' => $field . 'Unit', 'exportBy' => ['name']]));
                }
                continue;
            }

            $this->createExportConfiguratorItem($data);

            if (!empty($this->getConfig()->get('isMultilangActive')) && !empty($defs['isMultilang']) && empty($defs['measureId'])) {
                foreach ($defs['lingualFields'] ?? [] as $languageField) {
                    $this->createExportConfiguratorItem(array_merge($data, ['name' => $languageField]));
                }
            }

            if (in_array($type, ['int', 'float', 'varchar'])) {
                $hasMeasure = !empty($defs['measureId']);
                $hasPrefix  = !empty($defs['prefixEnabled']);

                if ($hasMeasure) {
                    $this->createExportConfiguratorItem(array_merge($data, ['name' => $field . 'Unit', 'exportBy' => ['name']]));
                }

                if ($hasPrefix) {
                    $this->createExportConfiguratorItem(array_merge($data, ['name' => $field . 'Prefix', 'exportBy' => ['name']]));
                }

                if ($hasMeasure || $hasPrefix) {
                    $prefix = $hasPrefix ? "{{ record['{$field}PrefixName'] }} " : '';
                    $unit   = $hasMeasure ? " {{ record['{$field}UnitName'] }}" : '';
                    $this->createExportConfiguratorItem(array_merge($data, [
                        'name'       => null,
                        'type'       => 'script',
                        'columnType' => 'custom',
                        'column'     => $languageObj->translate('combined' . ucfirst($field), 'fields', $feedEntity),
                        'script'     => "{$prefix}{{ record['{$field}'] }}{$unit}"
                    ]));
                }
            }
        }

        return true;
    }

    public function addAttributes(string $entityName, string $id, array $attributesIds): bool
    {
        if (!$this->getAcl()->check('ExportFeed', 'edit')) {
            throw new Exceptions\Forbidden();
        }

        if (!in_array($entityName, ['ExportFeed', 'Sheet'])) {
            throw new Exceptions\BadRequest('Wrong entity name');
        }

        $feed = $this->getEntityManager()->getRepository($entityName)->get($id);
        if (empty($feed)) {
            return false;
        }

        foreach ($this->prepareConfiguratorItemDataForAttributes($feed, $attributesIds) as $row) {
            $this->createExportConfiguratorItem($row);
        }

        return true;

    }

    public function prepareConfiguratorItemDataForAttributes(Entity $feed, array $attributesIds, ?string $contentLanguageCode = null): array
    {
        $result = [];

        $feedEntityName = $feed->get('entity') ?? $feed->getFeedField('entity');

        $feedEntity = $this->getEntityManager()->getRepository($feedEntityName)->get();

        foreach ($this->getAttributeFieldConverter()->getAttributesRowsByIds($attributesIds) as $attribute) {
            if (!empty($attribute['channel_name'])) {
                $attribute['name'] .= ' / ' . $attribute['channel_name'];
            }
            $attributesDefs = [];
            $this->getAttributeFieldConverter()->convert($feedEntity, $attribute, $attributesDefs);
            foreach ($attributesDefs as $field => $fieldDefs) {
                // When content language is set, filter multilingual attribute field variants.
                // code='' means main language: keep main fields, drop all variants.
                // code='de_DE' means specific language: drop main fields, keep matching variant only.
                if ($contentLanguageCode !== null) {
                    if (!empty($fieldDefs['isMultilang']) && $contentLanguageCode !== '') {
                        continue;
                    }
                    if (!empty($fieldDefs['multilangField'])) {
                        if (($fieldDefs['multilangLocale'] ?? null) !== $contentLanguageCode) {
                            continue;
                        }
                    }
                }

                $data = [
                    'name'                                 => $field,
                    'type'                                 => 'Field',
                    'columnType'                           => 'name',
                    'entityAttributeId'                    => $attribute['id'],
                    lcfirst($feed->getEntityName()) . 'Id' => $feed->get('id'),
                ];

                if (in_array($fieldDefs['type'], ['link', 'linkMultiple', 'file'])) {
                    $data['exportBy'] = [$fieldDefs['foreignName'] ?? 'name'];
                }

                if (in_array($fieldDefs['type'], ['rangeInt', 'rangeFloat'])) {
                    $result[] = array_merge($data, [
                        'name'       => null,
                        'type'       => 'script',
                        'columnType' => 'custom',
                        'column'     => $fieldDefs['label'],
                        'script'     => "{{ record['{$field}From'] }} - {{ record['{$field}To'] }} {{ record['{$field}UnitName'] }}",
                    ]);
                    continue;
                } else if (!empty($fieldDefs['combinedField'])) {
                    $result[] = $data;

                    $hasMeasure = !empty($fieldDefs['measureId']);
                    $hasPrefix  = !empty($fieldDefs['prefixEnabled']);
                    $prefix     = $hasPrefix ? "{{ record['{$field}PrefixName'] }} " : '';
                    $unit       = $hasMeasure ? " {{ record['{$field}UnitName'] }}" : '';

                    $result[] = array_merge($data, [
                        'name'       => null,
                        'type'       => 'script',
                        'columnType' => 'custom',
                        'column'     => $fieldDefs['detailViewLabel'] ?? $fieldDefs['label'],
                        'script'     => "{$prefix}{{ record['{$field}'] }}{$unit}",
                    ]);
                    continue;
                }

                $result[] = $data;
            }
        }

        return $result;
    }

    public function addAllAttributes(string $entityName, string $id): bool
    {
        if (!$this->getAcl()->check('ExportFeed', 'edit')) {
            throw new Exceptions\Forbidden();
        }

        if (!in_array($entityName, ['ExportFeed', 'Sheet'])) {
            throw new Exceptions\BadRequest('Wrong entity name');
        }

        $feed = $this->getEntityManager()->getRepository($entityName)->get($id);
        if (empty($feed)) {
            return false;
        }

        $exists = $this->getEntityManager()->getRepository('ExportConfiguratorItem')
            ->where([
                'type'                      => 'allAttributes',
                lcfirst($entityName) . 'Id' => $feed->get('id'),
            ])
            ->findOne();

        if (!empty($exists)) {
            throw new Exceptions\BadRequest($this->getLanguage()->translate('allAttributesAlreadyAdded', 'labels', 'ExportFeed'));
        }

        $data = [
            'name'                      => null,
            'type'                      => 'allAttributes',
            'columnType'                => 'custom',
            lcfirst($entityName) . 'Id' => $feed->get('id'),
            'channels'                  => ['withoutChannel'],
        ];

        $this->createExportConfiguratorItem($data);

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

    public function readEntity(string $id): ?IEntity
    {
        $this->getRepository()->fixLocaleIfNecessary($id);

        return parent::readEntity($id);
    }

    public function findLinkedEntities($id, $link, $params)
    {
        if ($link === 'configuratorItems' && !empty($exportFeed = $this->getEntity($id))) {
            $this->getRepository()->fixLocaleIfNecessary($exportFeed->get('id'));
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

    public function prepareFeedDataConfiguration(Entity $sheet, ?string $contentLanguageCode = null, ?string $contentLocaleId = null): array
    {
        if ($sheet->getEntityName() === 'ExportFeed') {
            /** @var ExportFeedEntity $feed */
            $feed       = $sheet;
            $entityName = $sheet->getFeedField('entity');
        } else {
            /** @var ExportFeedEntity $feed */
            $feed       = $sheet->get('exportFeed');
            $entityName = $sheet->get('entity');
        }

        $configuration = [];

        /** @var \Export\Services\ExportConfiguratorItem $eciService */
        $eciService = $this->getInjection('serviceFactory')->create('ExportConfiguratorItem');

        $effectiveLocaleId = $contentLocaleId ?? $feed->get('localeId');

        foreach ($this->getPreparedConfiguratorItems($feed, $sheet, $entityName, $contentLanguageCode, $effectiveLocaleId) as $item) {
            $row = [
                'id'                        => $item->get('id'),
                'columnType'                => $item->get('columnType'),
                'column'                    => $eciService->prepareColumnName($item, $effectiveLocaleId),
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
                'exportStaticListLabel'     => $item->get('exportStaticListLabel'),
                'localeId'                  => $effectiveLocaleId,
            ];
            if ($feed->get('type') === 'simple') {
                $row['convertCollectionToString'] = true;
                $row['convertRelationsToString']  = true;
            }

            if ($item->get('type') === 'Field') {
                $fieldName    = $item->get('name');
                $row['field'] = $fieldName;

                if (!empty($contentLanguageCode) && !empty($fieldName)) {
                    // Redirect language-neutral multilingual field to its language-specific variant.
                    // Language-pinned items (those already with a multilangLocale) are left untouched.
                    $fieldDefs = $this->getMetadata()->get("entityDefs.$entityName.fields.$fieldName");
                    if (!empty($fieldDefs['isMultilang'])) {
                        $row['field'] = $fieldName . ucfirst(Language::languageToField($contentLanguageCode));
                    }

                    // Redirect language-neutral multilingual fields inside exportBy to their
                    // language-specific variants. exportBy fields live on the foreign entity.
                    $exportBy = $item->get('exportBy');
                    if (!empty($exportBy) && is_array($exportBy)) {
                        $foreignEntityName = $this->getMetadata()->get("entityDefs.$entityName.links.$fieldName.entity")
                            ?? $this->getMetadata()->get("entityDefs.$entityName.fields.$fieldName.entity");
                        if (!empty($foreignEntityName)) {
                            $suffix          = ucfirst(Language::languageToField($contentLanguageCode));
                            $row['exportBy'] = array_map(function (string $byField) use ($foreignEntityName, $suffix): string {
                                $byFieldDefs = $this->getMetadata()->get("entityDefs.$foreignEntityName.fields.$byField");
                                if (!empty($byFieldDefs['isMultilang'])) {
                                    return $byField . $suffix;
                                }
                                return $byField;
                            }, $exportBy);
                        }
                    }
                }
            }

            $configuration[] = $row;
        }

        return $configuration;
    }

    public function prepareFeedData(ExportFeedEntity $feed, ?string $contentLanguageId = null): array
    {
        $result = $feed->toArray();
        foreach ($feed->getFeedFields() as $name => $value) {
            $result[$name]         = $value;
            $result['data']->$name = $value;
        }
        $result['decimalMark']       = $result['data']->decimalMark = $feed->getDecimalMark();
        $result['thousandSeparator'] = $result['data']->thousandSeparator = $feed->getThousandSeparator();

        $result['fileType'] = $feed->get('fileType');

        $contentLanguageCode = null;
        $contentLocaleId     = null;
        if (!empty($contentLanguageId)) {
            $resolved                            = $this->resolveContentLanguage($contentLanguageId, $feed->get('localeId') ?? '');
            $contentLanguageCode                 = $resolved['code'];
            $contentLocaleId                     = $resolved['localeId'];
            $result['data']->contentLanguageId   = $contentLanguageId;
            $result['data']->contentLanguageCode = $contentLanguageCode;
            $result['data']->contentLocaleId     = $contentLocaleId;
        }

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
                    'configuration'      => $this->prepareFeedDataConfiguration($sheet, $contentLanguageCode, $contentLocaleId)
                ];
            }
        } else {
            $result['data']->configuration = Json::decode(Json::encode($this->prepareFeedDataConfiguration($feed, $contentLanguageCode, $contentLocaleId)));
        }

        return $this
            ->getEventManager()
            ->dispatch('ExportFeedService', 'prepareFeedData', new Event(['feed' => $feed, 'result' => $result]))
            ->getArgument('result');
    }

    protected function resolveContentLanguage(string $contentLanguageId, string $fallbackLocaleId): array
    {
        $language = $this->getEntityManager()->getEntity('Language', $contentLanguageId);
        if (empty($language)) {
            return ['code' => null, 'localeId' => $fallbackLocaleId];
        }

        $realCode = $language->get('code');
        $code     = $language->get('role') === 'main' ? '' : $realCode;
        $locale   = $this->getEntityManager()->getRepository('Locale')->where(['code' => $realCode])->findOne();

        return [
            'code'     => $code,
            'localeId' => $locale ? $locale->get('id') : $fallbackLocaleId,
        ];
    }

    public function pushExport(array $data): bool
    {
        $name = $this->getLanguage()->translate('createExportJobs', 'additionalTranslates', 'ExportFeed');
        $name = sprintf($name, $data['feed']['name']);

        $priority = empty($data['feed']['priority']) ? 'Normal' : (string)$data['feed']['priority'];

        $this->getRepository()->updateLastTime($data['feed']['id'], new \DateTime());

        if (!empty($data['executeNow'])) {
            $data['ownerUserId'] = $this->getUser()->get('id');
            $data['priority']    = AbstractExportType::PRIORITIES[$priority];
            $this->getInjection('container')->get(ExportJobCreator::class)->runNow($data);
        } else {
            $data['exportJobCreatorId'] = Util::generateId();
            $jobEntity                  = $this->getEntityManager()->getEntity('Job');
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
                    $result[] = ['template' => $name, 'name' => $templateClass->getName()];
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
            'markForUnlinkedAttribute'  => 'N/A',
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

        $entityDefs = $requestData->fieldDefs
            ? json_decode(json_encode($requestData->fieldDefs), true)
            : $this->getMetadata()->get(['entityDefs', $scope, 'fields'], []);

        $configuration = [];

        foreach ($entityDefs as $field => $fieldDefs) {
            if (!empty($fieldDefs['attributeId']) && in_array($fieldDefs['type'] ?? null, ['int', 'float', 'varchar'])
                && !empty($fieldDefs['combinedField'])) {
                // Add field for attribute script
                $parts = explode(' ', $fieldDefs['label']);
                array_pop($parts);
                $entityDefs['combined' . ucfirst($field)] = [
                    'attributeId'            => $fieldDefs['attributeId'],
                    'label'                  => join(' ', $parts),
                    'mainField'              => $field,
                    'measureId'              => $fieldDefs['measureId'] ?? null,
                    'prefixEnabled'          => $fieldDefs['prefixEnabled'] ?? false,
                    'attributeCombinedField' => true,
                ];
            }
        }

        foreach ($entityDefs as $field => $fieldDefs) {
            if (!empty($fieldDefs['exportDisabled'])) {
                continue;
            }

            if (empty($requestData->exportAllField) && !in_array($field, $requestData->fieldList)) {
                continue;
            }

            $item           = $baseConfiguration;
            $item['field']  = $field;
            $item['id']     = Util::generateId();
            $item['column'] = $this->getLanguage()->translate($field, 'fields', $scope);

            if (in_array($fieldDefs['type'], ['link', 'linkMultiple'])) {
                $item['exportBy'] = ['name'];
            }

            if ($fieldDefs['type'] == 'file') {
                $item['exportBy'] = ['downloadUrl'];
            }

            if (!empty($fieldDefs['attributeId'])) {
                $item['entityAttributeId'] = $fieldDefs['attributeId'];
                $item['column']            = $fieldDefs['label'];
                if (!empty($fieldDefs['channelName'])) {
                    $item['column'] = "{$fieldDefs['label']} / {$fieldDefs['channelName']}";
                }
            }

            if (!empty($fieldDefs['attributeCombinedField']) ||
                (empty($fieldDefs['attributeId']) && !empty($fieldDefs['combinedField']))) {
                $item['type'] = 'script';
                $mainField    = $fieldDefs['mainField'];
                $hasPrefix    = !empty($fieldDefs['prefixEnabled']);
                $hasMeasure   = !empty($fieldDefs['measureId']);

                if ($hasPrefix && $hasMeasure) {
                    $item['script'] = "{{ record['{$mainField}PrefixName'] }} {{ record['{$mainField}'] }} {{ record['{$mainField}UnitName'] }}";
                } elseif ($hasPrefix) {
                    $item['script'] = "{{ record['{$mainField}PrefixName'] }} {{ record['{$mainField}'] }}";
                } else {
                    $item['script'] = "{{ record['{$mainField}'] }} {{ record['{$mainField}UnitName'] }}";
                }
            }

            if (in_array($fieldDefs['type'] ?? null, ['rangeInt', 'rangeFloat'])) {
                $item['type']   = 'script';
                $item['script'] = "{{ record['{$field}From'] }} - {{ record['{$field}To'] }} {{ record['{$field}UnitName'] }}";
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
                'markForUnlinkedAttribute'  => 'N/A',
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
                    'markForUnlinkedAttribute'  => 'N/A',
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
            $data                         = $item->toArray();
            $data['_duplicatingEntityId'] = $item->get('id');
            $data['exportFeedId']         = $entity->get('id');

            unset($data['id']);
            unset($data['createdAt']);
            unset($data['modifiedAt']);
            unset($data['createdById']);
            unset($data['modifiedById']);

            $this->getServiceFactory()->create('Sheet')->createEntity((object)$data);
        }
    }

    public function verifyFeedByCode(string $code)
    {
        $exportFeed = $this->getRepository()->where(['code' => $code])->findOne();
        if (empty($exportFeed)) {
            return 'Export Feed code is invalid';
        }

        $hasIdColumn = false;
        foreach ($exportFeed->configuratorItems as $configuratorItem) {
            if ($configuratorItem->get('column') == 'ID' || ($configuratorItem->get('name') == 'id' && empty($configuratorItem->get('column')))) {
                $hasIdColumn = true;
                break;
            }
        }

        if (!$hasIdColumn) {
            return 'This export feed has no ID column';
        }

        return 'Export feed is correctly configured';
    }

    public function getData($exportFeedCode, $offset)
    {
        $exportFeed = $this->getRepository()->where(['code' => $exportFeedCode])->findOne();
        if (empty($exportFeed)) {
            throw new Exceptions\NotFound();
        }
        $data = [
            'id'                => Util::generateId(),
            'feed'              => $this->prepareFeedData($exportFeed),
            'disableCacheChunk' => true
        ];

        $data['offset'] = !empty($offset) ? (int)$offset : 0;
        $data['limit']  = empty($data['feed']['limit']) ? \PHP_INT_MAX : $data['feed']['limit'];

        $exportService = $this->getExportTypeService($data['feed']['type']);
        $exportService->setData($data);

        return [
            "total"      => $exportService->getTotal(),
            "urlColumns" => $exportService->getUrlColumns(),
            "records"    => $exportService->exportEasyCatalogJson(),
        ];
    }

    protected function hasInvalidConfiguratorItems(ExportFeedEntity $exportFeed): bool
    {
        $this->putAttributesToMetadata($exportFeed->get('id'));

        $entityName = $exportFeed->getFeedField('entity');
        $items      = $this->getPreparedConfiguratorItems($exportFeed, $exportFeed, $entityName);

        foreach ($items as $item) {
            if (!in_array($item->get('type'), ['Field'])) {
                continue;
            }
            $fieldDefs = $this->getMetadata()->get("entityDefs.$entityName.fields.{$item->get('name')}");
            if (empty($fieldDefs)) {
                return true;
            }
        }

        return false;
    }

    public function putAttributesToMetadata(string $exportFeedId, ?array $feedData = null): void
    {
        if ($feedData) {
            $feedData = json_decode(json_encode($feedData), true);
        }

        $exportFeed = $this->getEntityManager()->getEntity('ExportFeed', $exportFeedId);
        if (empty($exportFeed)) {
            if ($feedData === null) {
                return;
            }
            $entityName = $feedData['entity'];
            $language   = $this->getInjection('container')->get('language');
        } else {
            $entityName      = $exportFeed->getFeedField('entity');
            $language        = self::getLocalizedLanguage($this->getInjection('container'), $exportFeed->get('localeId'));
            $currentLocaleId = $this->getUser()->get('localeId');
            $this->getUser()->set('localeId', $exportFeed->get('localeId'));
        }

        if (!empty($exportFeed) && !empty($exportFeed->get('hasMultipleSheets'))) {
            if ($feedData) {
                foreach ($feedData['sheets'] ?? [] as $sheet) {
                    $attributesIds = array_column($sheet['configuration'], 'entityAttributeId');
                    $attributesIds = array_values(array_unique(array_filter($attributesIds)));
                    if (!empty($attributesIds)) {
                        $attributes = $this->getEntityManager()->getRepository('Attribute')->getAttributesByIds($attributesIds);
                        foreach ($attributes as $row) {
                            $this->putAttributeToMetadata($sheet['entity'], $language, $row);
                        }
                    }
                }
            } else {
                foreach ($exportFeed->get('sheets') ?? [] as $sheet) {
                    $items         = $this->getPreparedConfiguratorItems($exportFeed, $sheet, $sheet->get('entity'));
                    $attributesIds = array_column($items->toArray(), 'entityAttributeId');
                    $attributesIds = array_values(array_unique(array_filter($attributesIds)));
                    if (!empty($attributesIds)) {
                        $attributes = $this->getEntityManager()->getRepository('Attribute')->getAttributesByIds($attributesIds);
                        foreach ($attributes as $row) {
                            $this->putAttributeToMetadata($sheet->get('entity'), $language, $row);
                        }
                    }
                }
            }
        } elseif (!empty($entityName) && $this->getMetadata()->get("scopes.$entityName.hasAttribute")) {
            if ($feedData) {
                $attributesIds = array_column($feedData['data']['configuration'] ?? [], 'entityAttributeId');
                $attributesIds = array_values(array_unique(array_filter($attributesIds)));
            } else {
                $items         = $this->getPreparedConfiguratorItems($exportFeed, $exportFeed, $entityName);
                $attributesIds = array_column($items->toArray(), 'entityAttributeId');
                $attributesIds = array_values(array_unique(array_filter($attributesIds)));
            }

            $attributes = $this->getEntityManager()->getRepository('Attribute')->getAttributesByIds($attributesIds);
            foreach ($attributes as $row) {
                $this->putAttributeToMetadata($entityName, $language, $row);
            }
        }

        if (isset($currentLocaleId)) {
            $this->getUser()->set('localeId', $currentLocaleId);
        }
    }

    public function putAttributeToMetadata(string $entityName, Language $language, array $attributeRow): void
    {
        $attributesDefs = [];
        if (!empty($attributeRow['channel_name'])) {
            $attributeRow['name'] = $attributeRow['name'] . ' / ' . $attributeRow['channel_name'];
        }

        $this
            ->getAttributeFieldConverter()
            ->convert($this->getEntityManager()->getEntity($entityName), $attributeRow, $attributesDefs);

        foreach ($attributesDefs as $name => $attributeDefs) {
            $this
                ->getMetadata()
                ->set('entityDefs', $entityName, ['fields' => [$name => $attributeDefs]]);

            $language->set($entityName, 'fields', $name, $attributeDefs['label']);
        }
    }

    protected function getPreparedConfiguratorItems(Entity $feed, Entity $sheet, string $entityName, ?string $contentLanguageCode = null, ?string $effectiveLocaleId = null): EntityCollection
    {
        $items = $this->getEntityManager()->getRepository('ExportConfiguratorItem')
            ->where([
                lcfirst($sheet->getEntityName()) . 'Id' => $sheet->get('id'),
            ])
            ->order('sortOrder')
            ->find();

        $collection = new EntityCollection([], 'ExportConfiguratorItem');
        foreach ($items as $item) {
            if ($item->get('type') === 'allAttributes') {
                $attributesIds = $this->getEntityManager()->getRepository('Attribute')->getAllAttributesIdsForEntity(
                    $entityName,
                    $feed->get('data')->where ?? [],
                    $item->get('channels')
                );

                if (empty($attributesIds)) {
                    continue;
                }

                foreach ($this->prepareConfiguratorItemDataForAttributes($feed, $attributesIds, $contentLanguageCode) as $row) {
                    $attributeItem = $this->getEntityManager()->getRepository('ExportConfiguratorItem')->get();
                    $attributeItem->set($row);
                    $attributeItem->id = $item->id;
                    $attributeItem->set('entity', $entityName);
                    $collection->append($attributeItem);
                }
            } else {
                $item->set('entity', $entityName);
                $collection->append($item);
            }
        }

        // put attributes to metadata
        $attributesIds = array_unique(array_filter(array_column($collection->toArray(), 'entityAttributeId')));
        if (!empty($attributesIds)) {
            $attributes = $this
                ->getEntityManager()
                ->getRepository('Attribute')
                ->getAttributesByIds(array_values($attributesIds));
            if (!empty($attributes)) {
                $localeId = $effectiveLocaleId ?? $feed->get('localeId');
                $language = self::getLocalizedLanguage($this->getInjection('container'), $localeId);

                $currentLocaleId = $this->getUser()->get('localeId') ?? $localeId;
                $this->getUser()->set('localeId', $localeId);

                foreach ($attributes as $row) {
                    $this->putAttributeToMetadata($entityName, $language, $row);
                }

                $this->getUser()->set('localeId', $currentLocaleId);
            }
        }

        return $collection;
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
