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

use Atro\Core\Container;
use Atro\Core\Exceptions\Error;
use Atro\Core\KeyValueStorages\StorageInterface;
use Atro\ORM\DB\RDB\Mapper;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Query\QueryBuilder;
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

    public function getSelectColumn(array $configuration): string
    {
        return 'id';
    }

    public function queryCallbackForAttribute(Container $container, QueryBuilder $qb, Mapper $mapper, array $conf): void
    {
        $mtAlias = $mapper->getQueryConverter()->getMainTableAlias();

        /** @var \Doctrine\DBAL\Connection $connection */
        $connection = $container->get('connection');

        $channelsIds = [''];
        if (!empty($conf['channelId'])) {
            $channelsIds[] = $conf['channelId'];
        }

        foreach ($channelsIds as $channelId) {
            $alias = "alias_{$conf['id']}_{$channelId}";
            $qb1 = $connection->createQueryBuilder()
                ->select("$alias.{$this->getSelectColumn($conf)}")
                ->from('product_attribute_value', $alias)
                ->where("$alias.attribute_id = :{$alias}_attributeId")
                ->andWhere("$alias.deleted = :false")
                ->andWhere("$alias.channel_id = :{$alias}_channelId")
                ->andWhere("$alias.language = :{$alias}_language")
                ->andWhere("$alias.product_id =$mtAlias.id")
                ->setParameter("{$alias}_attributeId", $conf['attributeId'])
                ->setParameter("{$alias}_channelId", $channelId)
                ->setParameter("{$alias}_language", $conf['language'])
                ->setParameter("false", false, ParameterType::BOOLEAN);

            $qb->addSelect("({$qb1->getSQL()}) AS {$conf['id']}_{$channelId}");
            foreach ($qb1->getParameters() as $pName => $pValue) {
                $qb->setParameter($pName, $pValue, $mapper::getParameterType($pValue));
            }
        }
    }

    public function queryCallback(Container $container, QueryBuilder $qb, Mapper $mapper, array $configuration): void
    {
        if (!empty($configuration['attributeId'])) {
            $this->queryCallbackForAttribute($container, $qb, $mapper, $configuration);
        }
    }

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
