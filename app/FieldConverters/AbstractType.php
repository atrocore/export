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
use Atro\Core\Utils\Database\DBAL\Schema\Converter;
use Atro\ORM\DB\RDB\Mapper;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Query\QueryBuilder;
use Espo\Core\Utils\Metadata;
use Espo\ORM\IEntity;
use Export\DataConvertor\Convertor;

abstract class AbstractType
{
    protected Convertor $convertor;

    public function __construct(Convertor $convertor)
    {
        $this->convertor = $convertor;
    }

    abstract public function convertToString(array &$result, array $record, array $configuration): void;

    public function queryCallbackForAttribute(Container $container, QueryBuilder $qb, Mapper $mapper, array $conf): void
    {
        echo '<pre>';
        print_r('123');
        die();

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

//            (SELECT GROUP_CONCAT(
//                CONCAT(IFNULL(pav1.id, 'N/A'), ':',
//                    IFNULL(pav1.bool_value, 'N/A'), ':',
//                    IFNULL(pav1.text_value, 'N/A')
//                ) SEPARATOR ', ')

////            $innerSql = str_replace([$mtAlias, 'mt_alias'], ['a_' . $uniqueHash, $mtAlias], $qb1->getSQL());
//
            if (Converter::isPgSQL($container->get('connection'))) {
                $qb1->select("STRING_AGG(
                    COALESCE($alias.id::text, 'N/A') || '::atro::' ||
                    COALESCE($alias.bool_value::text, 'N/A') || '::atro::' ||
                    COALESCE($alias.date_value::text, 'N/A') || '::atro::' ||
                    COALESCE($alias.datetime_value::text, 'N/A') || '::atro::' ||
                    COALESCE($alias.int_value::text, 'N/A') || '::atro::' ||
                    COALESCE($alias.int_value1::text, 'N/A') || '::atro::' ||
                    COALESCE($alias.float_value::text, 'N/A') || '::atro::' ||
                    COALESCE($alias.float_value1::text, 'N/A') || '::atro::' ||
                    COALESCE($alias.varchar_value::text, 'N/A') || '::atro::' ||
                    COALESCE($alias.reference_value::text, 'N/A') || '::atro::' ||
                    COALESCE($alias.text_value, 'N/A'),
                    ', ')");
            } else {
            }

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
