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

use Atro\Core\Utils\IdGenerator;
use Doctrine\DBAL\ParameterType;
use Atro\Core\Templates\Repositories\Base;
use Espo\ORM\Entity;

class ExportConfiguratorItem extends Base
{
    protected function beforeSave(Entity $entity, array $options = [])
    {
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
