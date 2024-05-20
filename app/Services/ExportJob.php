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

use Doctrine\DBAL\ParameterType;
use Espo\Core\Templates\Services\Base;
use Espo\ORM\Entity;

class ExportJob extends Base
{
    protected $mandatorySelectAttributeList = ['exportFeedId', 'exportFeedName', 'state', 'stateMessage'];

    public function deleteOld(int $days = 14): bool
    {
        if ($days === 0) {
            return true;
        }

        // delete
        while (true) {
            $toDelete = $this->getEntityManager()->getRepository('ExportJob')
                ->where(['modifiedAt<' => (new \DateTime())->modify("-$days days")->format('Y-m-d H:i:s')])
                ->limit(0, 2000)
                ->order('modifiedAt')
                ->find();
            if (empty($toDelete[0])) {
                break;
            }

            foreach ($toDelete as $entity) {
                $this->getEntityManager()->removeEntity($entity);
            }
        }

        // delete queue items
        while (true) {
            $toDeleteItem = $this->getEntityManager()->getRepository('QueueItem')
                ->where([
                    'modifiedAt<' => (new \DateTime())->modify("-$days days")->format('Y-m-d H:i:s'),
                    'serviceName' => ['ExportJobCreator', 'QueueManagerExport'],
                    'status' => ['Success', 'Failed', 'Canceled']
                ])
                ->limit(0, 2000)
                ->order('modifiedAt')
                ->find();
            if (empty($toDeleteItem[0])) {
                break;
            }

            foreach ($toDeleteItem as $entity) {
                $this->getEntityManager()->removeEntity($entity);
            }
        }

        // delete forever
        $daysToDeleteForever = $this->getConfig()->get('exportJobsDeletedMaxDays', 14);
        $qb = $this->getEntityManager()->getConnection()->createQueryBuilder();
        $qb
            ->delete('export_job')
            ->where('modified_at <= :maxDate')
            ->andWhere('deleted = :true')
            ->setParameter('maxDate', (new \DateTime())->modify("-$daysToDeleteForever days")->format('Y-m-d H:i:s'))
            ->setParameter('true', true, ParameterType::BOOLEAN)
            ->executeStatement();

        return true;
    }

    public function prepareEntityForOutput(Entity $entity)
    {
        parent::prepareEntityForOutput($entity);

        if (empty($feed = $entity->get('exportFeed'))) {
            return;
        }

        $entity->set('editable', $this->getAcl()->check($feed, 'edit'));
    }
}
