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

use Doctrine\DBAL\ParameterType;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Templates\Repositories\Base;
use Espo\ORM\Entity;
use Export\Core\ValueModifier;

class ExportConfiguratorItem extends Base
{
    protected function beforeSave(Entity $entity, array $options = [])
    {
        if ($entity->isNew() && !$entity->has('previousItem')) {
            $where = ['exportFeedId' => $entity->get('exportFeedId')];
            if (!empty($entity->get('sheetId'))) {
                $where = ['sheetId' => $entity->get('sheetId')];
            }
            $last = $this->select(['sortOrder'])->where($where)->order('sortOrder', 'DESC')->findOne();
            $entity->set('sortOrder', empty($last) ? 0 : $last->get('sortOrder') + 10);
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
