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

namespace Export\TwigFunction;

use Atro\Core\KeyValueStorages\MemoryStorage;
use Espo\Core\ORM\EntityManager;
use Espo\Core\ServiceFactory;

class GetSharedUrl extends AbstractTwigFunction
{
    public function __construct()
    {
        $this->addDependency('serviceFactory');
        $this->addDependency('entityManager');
        $this->addDependency('memoryStorage');;
    }

    public function run(string $fileId, ?string $type = null)
    {
        if (empty($fileId)) {
            return null;
        }

        if (empty($type)) {
            $type = 'download';
        }

        $exportJobId = $this->getMemoryStorage()->get('exportJobId');

        $exportJob = $this->getEntityManager()->getRepository('ExportJob')->get($exportJobId);
        if (empty($exportJob)) {
            return null;
        }

        $sharingRepo = $this->getEntityManager()->getRepository('Sharing');

        $where = [
            'fileId'       => $fileId,
            'exportFeedId' => $exportJob->get('exportFeedId')
        ];

        if (empty($sharing = $sharingRepo->where($where)->findOne())) {
            $sharing = $sharingRepo->get();
            $sharing->set($where);
            $sharingRepo->save($sharing);
        }

        $this->getServiceFactory()->create('Sharing')->prepareEntityForOutput($sharing);

        return $sharing->get($type === 'download' ? 'link' : 'viewLink');
    }

    /**
     * @return ServiceFactory
     */
    protected function getServiceFactory(): ServiceFactory
    {
        return $this->getInjection('serviceFactory');
    }

    protected function getEntityManager(): EntityManager
    {
        return $this->getInjection('entityManager');
    }

    protected function getMemoryStorage(): MemoryStorage
    {
        return $this->getInjection('memoryStorage');
    }
}
