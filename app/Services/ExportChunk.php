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

use Espo\ORM\Entity;
use Atro\Services\QueueManagerBase;

class ExportChunk extends QueueManagerBase
{
    public function run(array $data = []): bool
    {
        /** @var AbstractExportType $typeService */
        $typeService = $this->getContainer()->get('serviceFactory')->create('ExportFeed')
            ->getExportTypeService($data['feed']['type'], $data);

        $res = $typeService->createCacheChunk();

        $qiData = $this->qmItem->get('data');
        $qiData->chunkFileName = $res['fileName'];
        $qiData->files = $res['files'];

        $this->qmItem->set('data', $qiData);
        $this->getEntityManager()->saveEntity($this->qmItem);

        return true;
    }

    /**
     * @inheritDoc
     */
    public function getNotificationMessage(Entity $queueItem): string
    {
        return '';
    }
}
