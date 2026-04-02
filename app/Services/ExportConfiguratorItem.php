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
    protected $mandatorySelectAttributeList
        = [
            'exportFeedId',
            'sheetId',
            'entity',
            'type',
            'columnType',
            'exportBy',
            'channels',
            'exportIntoSeparateColumns',
            'sortOrder',
            'entityAttributeId',
            'fixedValue',
            'zip',
            'virtualFields'
        ];

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

        if ($entity->get('type') === 'Field') {
            // prepare field defs
            $fieldDefs = $this->getMetadata()->get("entityDefs.{$entity->get('entity')}.fields.{$entity->get('name')}");
            if (empty($fieldDefs)) {
                $this->getServiceFactory()->create('ExportFeed')->putAttributesToMetadata($feed->get('id'));
                $fieldDefs = $this->getMetadata()->get("entityDefs.{$entity->get('entity')}.fields.{$entity->get('name')}");
            }
            $entity->set('fieldDefs', $fieldDefs);
        }

        $entity->set('column', $this->prepareColumnName($entity));
        $entity->set('exportFeedData', $feed->toArray());
        $entity->set('editable', $this->getAcl()->check($feed, 'edit'));

        $entity->set('fileNameTemplate', $entity->getVirtualField('fileNameTemplate'));

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

    public function updateEntity($id, $data): bool
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
        return $this->prepareFieldColumnName($entity);
    }

    protected function prepareFieldColumnName(Entity $entity): string
    {
        if (!empty($entity->get('sheetId'))) {
            $sheet = $this->getEntityManager()->getEntity('Sheet', $entity->get('sheetId'));
            if (empty($sheet)) {
                throw new NotFound();
            }
            $exportFeedId = $sheet->get('exportFeedId');
        } else {
            $exportFeedId = $entity->get('exportFeedId');
        }

        switch ($entity->get('columnType') ?? 'name') {
            case 'name':
                $exportFeed = $this->getEntityManager()->getEntity('ExportFeed', $exportFeedId);
                $column = $this
                    ->getLocalizedLanguage($exportFeed->get('localeId'))
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
