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
use Atro\Core\Utils\Util;
use Export\Services\ExportFeed;

class ExportJobCreator extends AbstractJob implements JobInterface
{
    public function run(Job $job): void
    {
        $data = $job->getPayload();
        $data['ownerUserId'] = $job->get('ownerUserId');
        $data['priority'] = $job->get('priority');

        $this->runNow($data);
    }

    public function runNow(array $data): void
    {
        $data['offset'] = 0;
        $data['limit'] = empty($data['feed']['limit']) ? \PHP_INT_MAX : $data['feed']['limit'];

        if (!empty($data['feed']['originTemplateName'])) {
            $data['feed']['originTemplate'] = $this->getExportFeedService()->getOriginTemplate($data['feed']['originTemplateName']);
        }

        $count = $this->getExportFeedService()->getExportTypeService($data['feed']['type'], $data)->getTotal();

        if (!empty($data['feed']['separateJob']) && !empty($count)) {
            $i = 1;

            if ($data['limit'] > 2000) {
                while ($data['offset'] < $count) {
                    $jobName = $data['feed']['name'];
                    if ($count > $data['limit']) {
                        $jobName .= " ($i)";
                    }
                    $data['iteration'] = $i;
                    $this->pushExportJob($jobName, $data);
                    $data['offset'] = $data['offset'] + $data['limit'];
                    $i++;
                }
            } else {
                $offset = 0;
                $limit = 2000;

                $chunks = [];

                while (true) {
                    $ids = $this->getCollectionIds($data, $offset, $limit);
                    if (empty($ids)) {
                        break;
                    }
                    $chunks[] = $ids;
                    $offset += $limit;
                }

                foreach ($chunks as $ids) {
                    foreach (array_chunk($ids, $data['limit']) as $partIds) {
                        $data['entityIds'] = $partIds;
                        $jobName = $data['feed']['name'];
                        if ($count > $data['limit']) {
                            $jobName .= " ($i)";
                        }
                        $data['iteration'] = $i;
                        $this->pushExportJob($jobName, $data);
                        $data['offset'] = $data['offset'] + $data['limit'];
                        $i++;
                    }
                }
            }

        } else {
            $this->pushExportJob($data['feed']['name'], $data);
        }
    }

    protected function pushExportJob(string $jobName, array $data): string
    {
        $user = $this->getUser();

        $maxWorkers = $data['feed']['maxWorkers'] ?? null;

        $exportJob = $this->getEntityManager()->getEntity('ExportJob');
        $exportJob->id = Util::generateId();
        $exportJob->set('name', $jobName);
        $exportJob->set('exportFeedId', $data['feed']['id']);
        $exportJob->set('start', (new \DateTime())->format('Y-m-d H:i:s'));
        $exportJob->set('ownerUserId', $user->get('id'));
        $exportJob->set('assignedUserId', $user->get('id'));
        $exportJob->set('teamsIds', array_column($user->get('teams')->toArray(), 'id'));
        $exportJob->set('payload', $data);

        $data['exportJobId'] = $exportJob->get('id');

        $qmJobName = sprintf($this->translate('exportName', 'additionalTranslates', 'ExportFeed'), $jobName);

        if (!empty($data['executeNow'])) {
            $this->getEntityManager()->saveEntity($exportJob);
            $this->getContainer()->get(Export::class)->runNow($data);
        } else {
            if ($maxWorkers !== null && $maxWorkers > 0) {
                while ($this->getAmountOfAlreadyPendingJobs($data['exportJobCreatorId'] ?? null) >= $maxWorkers) {
                    sleep(1);
                }
            }

            $jobEntity = $this->getEntityManager()->getEntity('Job');
            $jobEntity->set([
                'name'        => $qmJobName,
                'type'        => 'Export',
                'payload'     => $data,
                'ownerUserId' => $data['ownerUserId'],
                'priority'    => $data['priority'],
            ]);
            $this->getEntityManager()->saveEntity($jobEntity);
            $exportJob->set('queueItemId', $jobEntity->get('id'));
            $this->getEntityManager()->saveEntity($exportJob);
        }

        return $exportJob->get('id');
    }

    protected function getCollectionIds(array $data, int $offset = null, int $limit = 2000): ?array
    {
        $data = json_decode(json_encode($data), true);
        $params = [
            'select' => ['id'],
            'sortBy' => 'id',
            'asc'    => true,
            'where'  => !empty($data['feed']['data']['where']) ? $data['feed']['data']['where'] : [],
        ];

        $params['offset'] = $offset;
        $params['maxSize'] = $limit;
        $params['withDeleted'] = !empty($data['feed']['data']['withDeleted']);

        if (!empty($data['feed']['sortOrderField'])) {
            $params['sortBy'] = $data['feed']['sortOrderField'];
            if ($this->getMetadata()->get(['entityDefs', $data['feed']['entity'], 'fields', $params['sortBy'], 'type']) === 'link') {
                $params['sortBy'] .= 'Id';
            }
            $params['asc'] = true;
            if (!empty($data['feed']['sortOrderDirection']) && $data['feed']['sortOrderDirection'] !== 'ASC') {
                $params['asc'] = false;
            }
        }

        $result = $this->getServiceFactory()->create($data['feed']['entity'])->findEntities($params);
        if (isset($result['collection']) && count($result['collection']) > 0) {
            return array_column($result['collection']->toArray(), 'id');
        }

        return null;
    }

    protected function getExportFeedService(): ExportFeed
    {
        return $this->getServiceFactory()->create('ExportFeed');
    }

    protected function getAmountOfAlreadyPendingJobs(?string $exportJobCreatorId): int
    {
        if (empty($exportJobCreatorId)) {
            return 0;
        }

        return $this->getEntityManager()->getRepository('Job')
            ->where([
                'status'   => [
                    "Pending",
                    "Running",
                ],
                'type'     => 'Export',
                'payload*' => '%"exportJobCreatorId":"'.$exportJobCreatorId.'"%',
            ])
            ->count();
    }
}
