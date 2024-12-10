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
use Export\Services\AbstractExportType;

class ExportChunk extends AbstractJob implements JobInterface
{
    public function run(Job $job): void
    {
        $data = $job->getPayload();

        $exportJob = $this->getEntityManager()->getEntity('ExportJob', $data['exportJobId']);
        if (empty($exportJob)) {
            return;
        }

        if (!in_array($exportJob->get('state'), ['Pending', 'Running'])) {
            return;
        }

        $this->getMemoryStorage()->set('exportJobId', $exportJob->get('id'));

        /** @var AbstractExportType $typeService */
        $typeService = $this->getServiceFactory()->create('ExportFeed')->getExportTypeService($data['feed']['type'], $data);

        $res = $typeService->createCacheChunk();

        $qiData = $job->get('payload');
        $qiData->chunkFileName = $res['fileName'];
        $qiData->files = $res['files'];

        $job->set('payload', $qiData);
        $this->getEntityManager()->saveEntity($job);
    }
}
