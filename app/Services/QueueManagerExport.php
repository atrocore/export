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

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Error;
use Espo\Core\Utils\Metadata;
use Espo\ORM\Entity;
use Atro\Services\QueueManagerBase;
use Export\Core\Exceptions\NothingToExport;

class QueueManagerExport extends QueueManagerBase
{
    public function run(array $data = []): bool
    {
        $exportJob = $this->getEntityManager()->getEntity('ExportJob', $data['exportJobId']);
        if (empty($exportJob)) {
            return false;
        }
        $exportJob->set('state', 'Running');
        $this->getEntityManager()->saveEntity($exportJob);

        try {
            /** @var AbstractExportType $typeService */
            $typeService = $this->getContainer()->get('serviceFactory')->create('ExportFeed')->getExportTypeService($data['feed']['type']);
            try {
                $file = $typeService->export($data, $exportJob);
            } catch (NothingToExport $e) {
            }
            if (!empty($file)) {
                $exportJob->set('fileId', $file->get('id'));
            }
            if ($exportJob->get('state') == 'Running') {
                $exportJob->set('state', 'Success');
            }
            $exportJob->set('end', (new \DateTime())->format('Y-m-d H:i:s'));
            $this->getEntityManager()->saveEntity($exportJob);
        } catch (\Throwable $e) {
            $exportJob->set('end', (new \DateTime())->format('Y-m-d H:i:s'));
            $exportJob->set('state', 'Failed');
            $exportJob->set('stateMessage', $e->getMessage());
            $this->getEntityManager()->saveEntity($exportJob);
//            $GLOBALS['log']->error('Export Error: ' . $e->getMessage());

            if (!empty($data['executeNow'])) {
                throw new BadRequest($e->getMessage());
            }

            return false;
        }

        return true;
    }

    /**
     * @inheritDoc
     */
    public function getNotificationMessage(Entity $queueItem): string
    {
        $message = parent::getNotificationMessage($queueItem);

        if ($queueItem->get('status') === 'Success') {
            try {
                $exportJob = $this->getEntityManager()->getEntity('ExportJob', $queueItem->get('data')->exportJobId);
            } catch (\Throwable $e) {
                $GLOBALS['log']->error('Export Notification Error: ' . $e->getMessage());
                return $message;
            }

            $message .= ' ' . sprintf($this->translate('exportDownloadNotification', 'labels', 'ExportJob'), $exportJob->get('fileId'));
        }

        return $message;
    }

    /**
     * @return Metadata
     */
    protected function getMetadata(): Metadata
    {
        return $this->getContainer()->get('metadata');
    }
}
