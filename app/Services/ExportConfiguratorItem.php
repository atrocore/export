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

use Atro\Core\Templates\Services\Base;
use Atro\Core\Utils\Language;
use Atro\Core\Utils\Util;
use Espo\ORM\Entity;

class ExportConfiguratorItem extends Base
{
    protected $mandatorySelectAttributeList
        = [
            'exportFeedId',
            'sheetId',
            'entity',
            'type',
            'columnType',
            'exportBy',
            'exportIntoSeparateColumns',
            'sortOrder',
            'attributeId',
            'language',
            'fallbackLanguage',
            'channelId',
            'channelName',
            'fixedValue',
            'zip',
            'attributeValue',
            'virtualFields'
        ];

    protected array $languages = [];

    public function prepareEntityForOutput(Entity $entity)
    {
        parent::prepareEntityForOutput($entity);

        if (empty($feed = $entity->get('exportFeed')) && empty($sheet = $entity->get('sheet'))) {
            return;
        }

        if (!empty($sheet)) {
            $entity->set('entity', $sheet->get('entity'));
            $feed = $sheet->get('exportFeed');
        } else {
            $entity->set('entity', $feed->getFeedField('entity'));
        }

        $entity->set('column', $this->prepareColumnName($entity));
        $entity->set('exportFeedData', $feed->toArray());
        $entity->set('isAttributeMultiLang', false);
        $entity->set('editable', $this->getAcl()->check($feed, 'edit'));

        $entity->set('fileNameTemplate', $entity->getVirtualField('fileNameTemplate'));

        if ($entity->get('type') === 'Attribute' && !empty($entity->get('attributeId'))) {
            $attribute = $this->getEntityManager()->getRepository('Attribute')->get($entity->get('attributeId'));
            if (!empty($attribute)) {
                $entity->set('attributeData', $attribute->toArray());
                $entity->set('isAttributeMultiLang', !empty($attribute->get('isMultilang')));
                $entity->set('attributeType', $attribute->get('type'));
                $entity->set('attributeCode', $attribute->get('code'));
            }
        }
    }

    public function updateEntity($id, $data)
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

    public function prepareColumnName(Entity $entity): string
    {
        if ($entity->get('type') === 'Attribute') {
            return $this->prepareAttributeColumnName($entity);
        }

        return $this->prepareFieldColumnName($entity);
    }


    protected function getExportFeed(\Export\Entities\ExportConfiguratorItem $entity): ?\Export\Entities\ExportFeed
    {
        $exportFeed = $entity->get('exportFeed');
        if (!empty($exportFeed)) {
            return $exportFeed;
        }
        if (!empty($entity->get('exportFeedId'))) {
            return $this->getEntityManager()->getEntity('ExportFeed', $entity->get('exportFeedId'));
        }
        return null;
    }

    protected function prepareFieldColumnName(Entity $entity): string
    {
        switch ($entity->get('columnType') ?? 'name') {
            case 'name':
                $exportFeed = $this->getExportFeed($entity);
                $column = $this
                    ->getLanguage(empty($exportFeed) ? '' : $exportFeed->get('localeId'))
                    ->translate($entity->get('name'), 'fields', $entity->get('entity'));
                break;
            case 'custom':
                $column = (string)$entity->get('column');
                break;
            default:
                $column = '-';
        }

        return $column;
    }

    protected function prepareAttributeColumnName(Entity $entity): string
    {
        if (empty($attribute = $entity->get('attribute'))) {
            return '-';
        }

        $columnType = $entity->get('columnType') ?? 'name';

        $column = (string)$entity->get('column');

        if ($columnType === 'name') {
            $column = $attribute->get('name');
            if (!empty($exportFeed = $this->getExportFeed($entity))) {
                if (!empty($locale = $this->getEntityManager()->getEntity('Locale', $exportFeed->get('localeId')))) {
                    $fieldName = 'name' . ucfirst(Util::toCamelCase(strtolower($locale->get('languageCode'))));
                    if ($this->getMetadata()->get("entityDefs.Attribute.fields.$fieldName")) {
                        if ($attribute->get($fieldName)) {
                            $column = $attribute->get($fieldName);
                        }
                    }
                }
            }
        }

        return (string)$column;
    }

    protected function init()
    {
        parent::init();

        $this->addDependency('language');
        $this->addDependency('container');
    }

    protected function getLanguage(string $locale): Language
    {
        if (!isset($this->languages[$locale])) {
            $this->languages[$locale] = new Language($this->getInjection('container'), $locale);
        }

        return $this->languages[$locale];
    }

    protected function getFieldsThatConflict(Entity $entity, \stdClass $data): array
    {
        return [];
    }
}
