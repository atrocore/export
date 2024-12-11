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

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Templates\Repositories\Base;
use Espo\ORM\Entity;
use Export\Services\AbstractExportType;

class ExportJob extends Base
{
    protected bool $cacheable = false;

    public function getExportJob(Entity $exportJob): ?Entity
    {
        if (empty($exportJob->get('queueItemId'))) {
            return null;
        }

        return $this->getEntityManager()->getRepository('Job')->get($exportJob->get('queueItemId'));
    }

    protected function init()
    {
        parent::init();

        $this->addDependency('language');
        $this->addDependency('fileManager');
    }

    protected function beforeSave(Entity $entity, array $options = [])
    {
        if ($entity->isNew()) {
            $last = $this->select(['sortOrder'])->where(['exportFeedId' => $entity->get('exportFeedId')])->order('sortOrder', 'DESC')->findOne();
            $entity->set('sortOrder', empty($last) ? 0 : $last->get('sortOrder') + 10);
        } else {
            if ($entity->isAttributeChanged('state')) {
                if ($entity->get('state') === 'Canceled' && !in_array($entity->getFetched('state'), ['Pending', 'Running'])) {
                    throw new BadRequest($this->getInjection('language')->translate('wrongJobState', 'exceptions', 'ExportJob'));
                }
                if ($entity->get('state') === 'Pending') {
                    if ($entity->getFetched('state') === 'Running') {
                        throw new BadRequest($this->getInjection('language')->translate('wrongJobState', 'exceptions', 'ExportJob'));
                    }
                    $qmJob = $this->getExportJob($entity);
                    if (empty($qmJob)) {
                        throw new BadRequest($this->getInjection('language')->translate('notExecutableJob', 'exceptions', 'ExportJob'));
                    }
                    $entity->set('start', (new \DateTime())->format('Y-m-d H:i:s'));
                }
            }
        }

        parent::beforeSave($entity, $options);
    }

    protected function afterSave(Entity $entity, array $options = [])
    {
        parent::afterSave($entity, $options);

        if ($entity->isAttributeChanged('state')) {
            $qmJob = $this->getExportJob($entity);
            if (!empty($qmJob)) {
                if ($entity->get('state') === 'Pending' && in_array($qmJob->get('status'), ['Success', 'Failed', 'Canceled'])) {
                    $this->toPendingQmJob($qmJob);
                }
                if ($entity->get('state') === 'Canceled') {
                    $this->cancelQmJob($qmJob);
                }
            }

            // delete tmp dir
            if (in_array($entity->get('state'), ['Success', 'Failed', 'Canceled'])) {
                $this->getInjection('fileManager')->removeAllInDir(AbstractExportType::TMP_DIR . DIRECTORY_SEPARATOR . $entity->get('id'));
            }

            // update last status
            $this->getEntityManager()->getRepository('ExportFeed')
                ->updateLastStatus($entity->get('exportFeedId'), $entity->get('state'));
        }

        if (!empty($feed = $entity->get('exportFeed'))) {
            $jobs = $this
                ->select(['id'])
                ->where([
                    'exportFeedId' => $feed->get('id'),
                    'state'        => ['Success', 'Failed', 'Canceled']
                ])
                ->order('createdAt', 'DESC')
                ->limit(2000, 100)
                ->find();
            foreach ($jobs as $job) {
                $this->getEntityManager()->removeEntity($job);
            }
        }
    }

    protected function afterRemove(Entity $entity, array $options = [])
    {
        if (!empty($file = $entity->get('file'))) {
            $this->getEntityManager()->removeEntity($file);
        }

        $data = $entity->getData();
        if (isset($data['fullFileName']) && file_exists($data['fullFileName'])) {
            unlink($data['fullFileName']);
        }

        $qmJob = $this->getExportJob($entity);
        if (!empty($qmJob)) {
            $this->cancelQmJob($qmJob);
            $this->getEntityManager()->removeEntity($qmJob);
        }

        parent::afterRemove($entity, $options);
    }

    protected function toPendingQmJob(Entity $job): void
    {
        $job->set('status', 'Pending');
        $this->getEntityManager()->saveEntity($job);
    }

    protected function cancelQmJob(Entity $job): void
    {
        if (in_array($job->get('status'), ['Pending', 'Running'])) {
            $job->set('status', 'Canceled');
            $this->getEntityManager()->saveEntity($job);

            if (!empty($job->get('payload')->exportJobId)) {
                $queueItems = $this->getEntityManager()->getRepository('Job')
                    ->where([
                        'payload*' => '%"exportJobId":"' . $job->get('payload')->exportJobId . '"%',
                        'status'   => ['Pending', 'Running']
                    ])
                    ->find();
                foreach ($queueItems as $qi) {
                    if (!empty($qi->get('payload')->chunkJob)) {
                        $qi->set('status', 'Canceled');
                        $this->getEntityManager()->saveEntity($qi);
                    }
                }
            }
        }
    }
}
