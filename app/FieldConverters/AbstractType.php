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

namespace Export\FieldConverters;

use Atro\Core\Exceptions\Error;
use Atro\Core\KeyValueStorages\StorageInterface;
use Espo\Core\Utils\Metadata;
use Export\DataConvertor\Convertor;

abstract class AbstractType
{
    protected Convertor $convertor;

    public function __construct(Convertor $convertor)
    {
        $this->convertor = $convertor;
    }

    abstract public function convertToString(array &$result, array $record, array $configuration): void;

    protected function getMemoryStorage(): StorageInterface
    {
        return $this->convertor->getEntityManager()->getMemoryStorage();
    }

    protected function getMetadata(): Metadata
    {
        return $this->convertor->getMetadata();
    }

    protected function getSharedDownloadUrl(string $exportJobId, string $fileId): string
    {
        $exportJob = $this->convertor->getEntityManager()->getRepository('ExportJob')->get($exportJobId);
        if (empty($exportJob)) {
            throw new Error("ExportJob '$exportJobId' does not exist.");
        }

        /** @var \Espo\Repositories\Sharing $sharingRepo */
        $sharingRepo = $this->convertor->getEntityManager()->getRepository('Sharing');

        $where = [
            'fileId'       => $fileId,
            'exportFeedId' => $exportJob->get('exportFeedId')
        ];

        if (empty($sharing = $sharingRepo->where($where)->findOne())) {
            $sharing = $sharingRepo->get();
            $sharing->set($where);
            $sharingRepo->save($sharing);
        }

        $this->convertor->getService('Sharing')->prepareEntityForOutput($sharing);

        return $sharing->get('link');
    }
}
