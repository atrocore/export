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
        $data = $job->getPayload();

        $exportJob = $this->getEntityManager()->getEntity('ExportJob', $data['exportJobId']);
        if (empty($exportJob)) {
            return;
        }
        $exportJob->set('state', 'Running');
        $this->getEntityManager()->saveEntity($exportJob);

        try {
            /** @var AbstractExportType $typeService */
            $typeService = $this->getServiceFactory()->create('ExportFeed')->getExportTypeService($data['feed']['type']);
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

            $this->createNotification($job, sprintf($this->translate('exportDownloadNotification', 'labels', 'ExportJob'), $exportJob->get('fileId')));
        } catch (\Throwable $e) {
            $exportJob->set('end', (new \DateTime())->format('Y-m-d H:i:s'));
            $exportJob->set('state', 'Failed');
            $exportJob->set('stateMessage', $e->getMessage());
            $this->getEntityManager()->saveEntity($exportJob);

            if (!empty($data['executeNow'])) {
                throw new BadRequest($e->getMessage());
            }
        }
    }
}
