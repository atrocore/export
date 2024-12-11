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

use Atro\Core\EventManager\Event;
use Atro\Listeners\AbstractListener;
use Espo\ORM\Entity;

class JobEntity extends AbstractListener
{
    public function afterSave(Event $event): void
    {
        // prepare entity
        $entity = $event->getArgument('entity');

        if (!empty($entity->get('payload')->exportJobId)) {
            $this->updateExportJob($entity);
        }
    }

    public function afterRemove(Event $event): void
    {
        // prepare entity
        $entity = $event->getArgument('entity');

        if (!empty($entity->get('payload')->exportJobId)) {
            $this->removeExportJob($entity);
        }
    }

    protected function updateExportJob(Entity $entity): bool
    {
        $exportJob = $this->getEntityManager()->getEntity('ExportJob', $entity->get('payload')->exportJobId);
        if (empty($exportJob)) {
            return false;
        }

        if ($entity->isAttributeChanged('status') && empty($entity->get('payload')->chunkJob)) {
            if ($entity->get('status') !== 'Success' && $exportJob->get('state') !== $entity->get('status')) {
                $exportJob->set('state', $entity->get('status'));
                $this->getEntityManager()->saveEntity($exportJob);
            }
        }

        return true;
    }

    protected function removeExportJob(Entity $entity): bool
    {
        $exportJob = $this->getEntityManager()->getEntity('ExportJob', $entity->get('payload')->exportJobId);

        if (empty($exportJob)) {
            return false;
        }

        if ($entity->get('status') == 'Pending') {
            $this->getEntityManager()->removeEntity($exportJob);
        }

        return true;
    }
}
