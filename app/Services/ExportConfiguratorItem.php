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

use Atro\Core\Exceptions\NotFound;
use Atro\Core\Templates\Services\Base;
use Atro\Core\Utils\Language;
use Atro\Core\Utils\Util;
use Espo\ORM\Entity;

class ExportConfiguratorItem extends Base
{
    private array $translationsCache = [];

    protected $mandatorySelectAttributeList
        = [
            'exportFeedId',
            'sheetId',
            'entity',
            'type',
            'data',
            'exportBy',
            'channels',
            'exportIntoSeparateColumns',
            'sortOrder',
            'entityAttributeId',
            'fixedValue',
            'zip',
            'selectedLanguageOnly'
        ];

    public function prepareEntityForOutput(Entity $entity)
    {
        parent::prepareEntityForOutput($entity);

        $this->getRepository()->setDataFields($entity);

        if (empty($feed = $entity->get('exportFeed')) && empty($sheet = $entity->get('sheet'))) {
            return;
        }

        if (!empty($sheet)) {
            $entity->set('entity', $sheet->get('entity'));
            $feed = $sheet->get('exportFeed');
        } else {
            $entity->set('entity', $feed->getFeedField('entity'));
        }

        if ($entity->get('type') === 'Field' && $entity->get('name') !== 'id') {
            // prepare field defs
            $fieldDefs = $this->getMetadata()->get("entityDefs.{$entity->get('entity')}.fields.{$entity->get('name')}");
            if (empty($fieldDefs)) {
                $this->getServiceFactory()->create('ExportFeed')->putAttributesToMetadata($feed->get('id'));
                $fieldDefs = $this->getMetadata()->get("entityDefs.{$entity->get('entity')}.fields.{$entity->get('name')}");
            }
            $entity->set('fieldDefs', $fieldDefs);

            if (empty($fieldDefs)) {
                $entity->set('isInvalid', true);
            }
        }

        $numberOfHeaders = (int)($feed->get('numberOfHeaders') ?? 1);
        $entity->set('exportFeedNumberOfHeaders', $numberOfHeaders);
        foreach ($this->prepareColumnNames($entity, $numberOfHeaders) as $index => $columnName) {
            $entity->set('headerText' . ($index + 1), $columnName);
        }

        $entity->set('exportFeedData', $feed->toArray());
        $entity->set('editable', $this->getAcl()->check($feed, 'edit'));

        if ($entity->get('type') === 'allAttributes') {
            $attributesIds = $this->getEntityManager()->getRepository('Attribute')
                ->getAllAttributesIdsForEntity(
                    $entity->get('entity'),
                    $feed->get('data')->where ?? [],
                    $entity->get('channels') ?? []
                );

            $entity->set('allAttributesCount', count($attributesIds));
        }
    }

    public function updateEntity(string $id, \stdClass $data): bool
    {
        if (property_exists($data, '_previousItemId') && property_exists($data, '_itemId')) {
            $data->previousItem = $data->_previousItemId;
            unset($data->_previousItemId);
            unset($data->_itemId);
        }

        return parent::updateEntity($id, $data);
    }

    protected function isEntityUpdated(Entity $entity, \stdClass $data): bool
    {
        if (property_exists($data, 'sortOrder')) {
            return true;
        }

        return parent::isEntityUpdated($entity, $data);
    }

    public function prepareColumnNames(Entity $entity, int $numberOfHeaders, ?string $localeId = null): array
    {
        $result = [];
        for ($k = 1; $k <= $numberOfHeaders; $k++) {
            $result[] = $this->prepareColumnNameForIndex($entity, $k, $localeId);
        }

        return $result;
    }

    protected function prepareColumnNameForIndex(Entity $entity, int $index, ?string $localeId): string
    {
        $columnType = $entity->get('headerProperty' . $index) ?? 'name';

        if ($columnType === 'custom') {
            return (string)$entity->get('headerText' . $index);
        }

        if ($entity->get('type') === 'allAttributes') {
            return '';
        }

        $localeId = $this->resolveLocaleId($entity, $localeId);

        switch ($columnType) {
            case 'name':
                if (!empty($entity->get('name'))){
                    return $this->translateFieldColumnName($localeId, $entity->get('entity'), $entity->get('name'));
                }
            case 'code':
                return (string)$entity->get('name');
            default:
                return !empty($entity->get('entityAttributeId'))
                    ? $this->resolveAttributePropertyColumnName($entity->get('entityAttributeId'), $columnType)
                    : $this->resolveEntityFieldPropertyColumnName($entity->get('entity'), $entity->get('name'), $columnType);
        }
    }

    /**
     * Resolves an arbitrary Attribute-entity property (anything beyond name/code) as header text.
     * link/linkMultiple properties resolve to the foreign record's name(s), matching the default
     * exportBy=['name'] behavior FieldConverters\LinkType/LinkMultipleType already use for exporting
     * attribute VALUES of those types.
     */
    protected function resolveAttributePropertyColumnName(string $attributeId, string $property): string
    {
        $attribute = $this->getEntityManager()->getEntity('Attribute', $attributeId);
        if (empty($attribute) || !$attribute->hasAttribute($property)) {
            return '';
        }

        $fieldType = $this->getMetadata()->get("entityDefs.Attribute.fields.$property.type");

        if ($fieldType === 'linkMultiple') {
            $names = [];
            foreach ($attribute->get($property) ?? [] as $related) {
                $names[] = (string)$related->get('name');
            }

            return implode(', ', $names);
        }

        if ($fieldType === 'link') {
            $related = $attribute->get($property);

            return $related === null ? '' : (string)$related->get('name');
        }

        return (string)$attribute->get($property);
    }

    /**
     * Resolves an arbitrary EntityField property (anything beyond name/code, e.g. type/pattern/
     * default/defaultUnit/foreignCode) as header text for a plain Field item (no entityAttributeId).
     * Reuses Atro\Repositories\EntityField's own item-preparation (via its generic get($id) with the
     * "{entity}_{field}" composite id it expects) instead of re-deriving each property's resolution -
     * e.g. foreignCode is computed there from the field's LINK defs, not its own field defs.
     */
    protected function resolveEntityFieldPropertyColumnName(string $entityType, string $fieldName, string $property): string
    {
        $entityField = $this->getEntityManager()->getRepository('EntityField')->get("{$entityType}_{$fieldName}");
        if (empty($entityField) || !$entityField->hasAttribute($property)) {
            return '';
        }

        return (string)$entityField->get($property);
    }

    protected function resolveLocaleId(Entity $entity, ?string $localeId): string
    {
        if ($localeId !== null) {
            return $localeId;
        }

        if (!empty($entity->get('sheetId'))) {
            $sheet = $this->getEntityManager()->getEntity('Sheet', $entity->get('sheetId'));
            if (empty($sheet)) {
                throw new NotFound();
            }
            $exportFeedId = $sheet->get('exportFeedId');
        } else {
            $exportFeedId = $entity->get('exportFeedId');
        }

        $exportFeed = $this->getEntityManager()->getEntity('ExportFeed', $exportFeedId);

        return $exportFeed->get('localeId');
    }

    protected function translateFieldColumnName(string $localeId, string $entity, string $field, string $category = 'fields'): string
    {
        if (!isset($this->translationsCache[$localeId])) {
            $this->translationsCache[$localeId] = $this->getLocalizedLanguage($localeId)->getAll();
        }

        return $this->translationsCache[$localeId][$entity][$category][$field]
            ?? $this->getLocalizedLanguage($localeId)->translate($field, $category, $entity);
    }

    protected function getLocalizedLanguage(string $locale): Language
    {
        return ExportFeed::getLocalizedLanguage($this->getInjection('container'), $locale);
    }

    protected function init()
    {
        parent::init();

        $this->addDependency('language');
        $this->addDependency('container');
    }

    protected function getFieldsThatConflict(Entity $entity, \stdClass $data): array
    {
        return [];
    }
}
