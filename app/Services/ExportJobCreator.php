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

use Atro\Core\QueueManager;
use Espo\Core\ServiceFactory;
use Espo\Core\Utils\Metadata;
use Espo\Core\Utils\Util;
use Espo\Entities\User;
use Atro\Services\QueueManagerBase;

class ExportJobCreator extends QueueManagerBase
{
    public function run(array $data = []): bool
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

        return true;
    }

    protected function pushExportJob(string $jobName, array $data): string
    {
        /** @var User $user */
        $user = $this->getInjection('user');

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

        $md5Hash = md5(json_encode($data['feed']) . $data['offset'] . $data['limit']);

        $priority = empty($data['feed']['priority']) ? 'Normal' : (string)$data['feed']['priority'];

        if (!empty($data['executeNow'])) {
            $this->getEntityManager()->saveEntity($exportJob);
            $this->getServiceFactory()->create('QueueManagerExport')->run($data);
        } else {
            $qmId = $this->getQM()->createQueueItem($qmJobName, 'QueueManagerExport', $data, $priority, $md5Hash);
            $exportJob->set('queueItemId', $qmId);
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

    protected function getServiceFactory(): ServiceFactory
    {
        return $this->getContainer()->get('serviceFactory');
    }

    protected function getQM(): QueueManager
    {
        return $this->getContainer()->get('queueManager');
    }

    protected function getMetadata(): Metadata
    {
        return $this->getContainer()->get('metadata');
    }
}
