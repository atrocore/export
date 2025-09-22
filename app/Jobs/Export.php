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

namespace Export\Jobs;

use Atro\Entities\Job;
use Atro\Jobs\AbstractJob;
use Atro\Jobs\JobInterface;
use Atro\Core\Exceptions\BadRequest;
use Export\Core\Exceptions\NothingToExport;
use Export\Services\AbstractExportType;

class Export extends AbstractJob implements JobInterface
{
    public function run(Job $job): void
    {
        $this->runNow($job->getPayload(), $job);
    }

    public function runNow(array $data, ?Job $job = null): void
    {
        $exportJob = $this->getEntityManager()->getEntity('ExportJob', $data['exportJobId']);
        if (empty($exportJob)) {
            return;
        }
        $exportJob->set('state', 'Running');
        $this->getEntityManager()->saveEntity($exportJob);

        $this->getMemoryStorage()->set('exportJob', $exportJob);

        $entityName = $data['feed']['entity'] ?? null;

        try {
            /** @var \Export\Services\ExportFeed $exportFeedService */
            $exportFeedService = $this->getServiceFactory()->create('ExportFeed');

            // put attributes to metadata as fields
            if (!empty($entityName) && $this->getMetadata()->get("scopes.$entityName.hasAttribute")) {
                $exportFeedService->putAttributesToMetadata($exportJob->get('exportFeedId'), $data['feed']);
            }

            $typeService = $exportFeedService->getExportTypeService($data['feed']['type']);
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

            if (!empty($file)) {
                $this->createExportNotification(sprintf($this->translate('exportDownloadNotification', 'labels', 'ExportJob'), $file->get('id')), $job);
            }

            $this->getMemoryStorage()->delete('exportJob');
        } catch (\Throwable $e) {
            $exportJob->set('end', (new \DateTime())->format('Y-m-d H:i:s'));
            $exportJob->set('state', 'Failed');
            $exportJob->set('stateMessage', $e->getMessage());
            $this->getEntityManager()->saveEntity($exportJob);

            $this->getMemoryStorage()->delete('exportJob');

            if (!empty($data['executeNow'])) {
                throw new BadRequest($e->getMessage());
            }
        }
    }

    protected function createExportNotification(string $message, ?Job $job = null): void
    {
        $ownerUserId = $this->getUser()->get('id');

        $notification = $this->getEntityManager()->getEntity('Notification');
        $notification->set('type', 'Message');
        $notification->set('message', $message);

        if (!empty($job)) {
            $notification->set('relatedType', 'Job');
            $notification->set('relatedId', $job->get('id'));
            $ownerUserId = $job->get('ownerUserId');
        }

        $notification->set('userId', $ownerUserId);

        if ($ownerUserId !== 'system') {
            $this->getEntityManager()->saveEntity($notification);
        }
    }
}
