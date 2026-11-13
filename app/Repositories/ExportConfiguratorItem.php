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

namespace Export\Repositories;

use Atro\Core\Exceptions\BadRequest;
use Doctrine\DBAL\ParameterType;
use Atro\Core\Templates\Repositories\Base;
use Espo\ORM\Entity;

class ExportConfiguratorItem extends Base
{
    public function get($id = null)
    {
        $entity = parent::get($id);
        if (!empty($entity)) {
            $this->setDataFields($entity);
        }

        return $entity;
    }

    /**
     * headerText1/headerProperty1, fileNameTemplate, exportStaticListLabel, ... are notStorable/
     * dataField fields (see Export\Entities\ExportConfiguratorItem) - re-set() every value already
     * packed into the `data` json column so it lands in valuesContainer, otherwise Entity::has()
     * returns false for it and Entity::toArray() silently drops it from output (see
     * Atro\Repositories\Connection for the same pattern).
     */
    public function setDataFields(Entity $entity): void
    {
        foreach ($entity->getDataFields() as $name => $value) {
            $entity->set($name, $value);
        }
    }

    protected function validateSortFieldRelation(Entity $entity): void
    {
        $value = (string)$entity->get('sortFieldRelation');
        if ($value === '' || $value === 'id' || !$entity->isAttributeChanged('sortFieldRelation')) {
            return;
        }

        // sortFieldRelation is only used for linkMultiple fields; for any other field it is never put into SQL, so skip
        if ($this->getMetadata()->get(['entityDefs', $entity->get('entity'), 'fields', $entity->get('name'), 'type']) !== 'linkMultiple') {
            return;
        }

        $relatedEntity = $this->getMetadata()->get(['entityDefs', $entity->get('entity'), 'links', $entity->get('name'), 'entity']);
        $notAllowedTypes = ['jsonObject', 'linkMultiple'];

        $isAllowedField = function (?string $scope, string $field) use ($notAllowedTypes): bool {
            if (empty($scope)) {
                return false;
            }
            $name = str_ends_with($field, 'Id') ? substr($field, 0, -2) : $field;
            $defs = $this->getMetadata()->get(['entityDefs', $scope, 'fields', $name]);
            if (empty($defs) || !empty($defs['notStorable']) || !empty($defs['exportDisabled']) || in_array($defs['type'], $notAllowedTypes, true)) {
                return false;
            }
            return !str_ends_with($field, 'Id') || in_array($defs['type'], ['link', 'file'], true);
        };

        $allowed = false;
        if (!empty($relatedEntity)) {
            if (strpos($value, '.') !== false) {
                [$link, $sub] = explode('.', $value, 2);
                $linkDefs = $this->getMetadata()->get(['entityDefs', $relatedEntity, 'fields', $link]);
                if (!empty($linkDefs) && in_array($linkDefs['type'] ?? '', ['link', 'file'], true)) {
                    $foreignEntity = $this->getMetadata()->get(['entityDefs', $relatedEntity, 'links', $link, 'entity']);
                    $allowed = $isAllowedField($foreignEntity, $sub);
                }
            } else {
                $allowed = $isAllowedField($relatedEntity, $value);
            }
        }

        if (!$allowed) {
            throw new BadRequest(sprintf($this->getLanguage()->translate('filterContainsUnknownField', 'exceptions', 'Global'), $value));
        }
    }

    protected function beforeSave(Entity $entity, array $options = [])
    {
        $this->validateSortFieldRelation($entity);

        if ($entity->isNew() && !$entity->has('previousItem')) {
            $field = 'export_feed_id';
            $value = $entity->get('exportFeedId');
            if (!empty($entity->get('sheetId'))) {
                $field = 'sheet_id';
                $value = $entity->get('sheetId');
            }
            $result = $this->getEntityManager()->getConnection()->createQueryBuilder()
                ->select('MAX(sort_order) as max_sort_order')
                ->from('export_configurator_item')
                ->where('deleted = :false')
                ->andWhere("$field = :fieldValue")
                ->setParameter('fieldValue', $value)
                ->setParameter('false', false, ParameterType::BOOLEAN)
                ->fetchAssociative();

            $lastSortOrder = $result['max_sort_order'] ?? 0;
            $entity->set('sortOrder', $lastSortOrder + 10);
        }


        parent::beforeSave($entity, $options);
    }

    public function updatePosition(Entity $entity): void
    {
        $qb = $this
            ->getConnection()
            ->createQueryBuilder()
            ->select('id')
            ->from('export_configurator_item')
            ->where('deleted=:false')
            ->setParameter('false', false, ParameterType::BOOLEAN);

        if (!empty($entity->get('sheetId'))) {
            $qb->andWhere('sheet_id=:sheetId')->setParameter('sheetId', $entity->get('sheetId'));
        } else {
            $qb->andWhere('export_feed_id=:exportFeedId')->setParameter('exportFeedId', $entity->get('exportFeedId'));
        }

        $res = $qb->orderBy('sort_order', 'ASC')->fetchFirstColumn();

        $ids = [];
        if (empty($entity->get('previousItem'))) {
            $ids[] = $entity->get('id');
        }

        foreach ($res as $id) {
            if (!in_array($id, $ids)) {
                $ids[] = $id;
            }
            if ($id === $entity->get('previousItem')) {
                $ids[] = $entity->get('id');
            }
        }

        foreach ($ids as $k => $id) {
            $this
                ->getConnection()
                ->createQueryBuilder()
                ->update('export_configurator_item')
                ->set('sort_order', ':sortOrder')->setParameter('sortOrder', $k * 10)
                ->where('id=:id')->setParameter('id', $id)
                ->executeQuery();
        }
    }

    protected function afterSave(Entity $entity, array $options = [])
    {
        parent::afterSave($entity, $options);

        if ($entity->isAttributeChanged('previousItem')) {
            $this->updatePosition($entity);
        }
    }

    protected function init()
    {
        parent::init();

        $this->addDependency('language');
    }
}
