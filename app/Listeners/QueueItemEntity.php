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

namespace Export\Listeners;

use Espo\ORM\Entity;
use Atro\Listeners\AbstractListener;
use Atro\Core\EventManager\Event;

/**
 * Class QueueItemEntity
 */
class QueueItemEntity extends AbstractListener
{
    /**
     * @param Event $event
     */
    public function afterSave(Event $event)
    {
        // prepare entity
        $entity = $event->getArgument('entity');

        if (!empty($entity->get('data')->exportJobId)) {
            $this->updateExportJob($entity);
        }
    }

    /**
     * @param Event $event
     */
    public function afterRemove(Event $event)
    {
        // prepare entity
        $entity = $event->getArgument('entity');

        if (!empty($entity->get('data')->exportJobId)) {
            $this->removeExportJob($entity);
        }
    }

    /**
     * @param Entity $entity
     *
     * @return bool
     * @throws \Espo\Core\Exceptions\Error
     */
    protected function updateExportJob(Entity $entity): bool
    {
        $exportJob = $this->getEntityManager()->getEntity('ExportJob', $entity->get('data')->exportJobId);
        if (empty($exportJob)) {
            return false;
        }

        if ($entity->isAttributeChanged('status')) {
            if (empty($entity->get('data')->chunkJob)) {
                if ($entity->get('status') !== 'Success' && $exportJob->get('state') !== $entity->get('status')) {
                    $exportJob->set('state', $entity->get('status'));
                    $this->getEntityManager()->saveEntity($exportJob);
                }
            } else {
                if ($entity->get('status') === 'Canceled') {
                    $queueItems = $this->getEntityManager()->getRepository('QueueItem')
                        ->where([
                            'data*'  => '%"exportJobId":"' . $exportJob->get('id') . '"%',
                            'status' => ['Pending', 'Running']
                        ])
                        ->find();
                    foreach ($queueItems as $qi) {
                        if (!empty($qi->get('data')->chunkJob)) {
                            $qi->set('status', 'Canceled');
                            $this->getEntityManager()->saveEntity($qi);
                        }
                    }
                }
            }
        }

        return true;
    }

    /**
     * @param Entity $entity
     *
     * @return bool
     */
    protected function removeExportJob(Entity $entity): bool
    {
        $exportJob = $this->getEntityManager()->getEntity('ExportJob', $entity->get('data')->exportJobId);

        if (empty($exportJob)) {
            return false;
        }

        if ($entity->get('status') == 'Pending') {
            $this->getEntityManager()->removeEntity($exportJob);
        }

        return true;
    }
}
