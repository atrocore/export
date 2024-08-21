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
use Atro\ORM\DB\RDB\Mapper;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Query\QueryBuilder;

class BoolType extends AbstractType
{
    public function queryCallback(Container $container, QueryBuilder $qb, Mapper $mapper, array $configuration): void
    {
        if (empty($configuration['attributeId'])) {
            return;
        }

        $mtAlias = $mapper->getQueryConverter()->getMainTableAlias();

        $selectColumn = 'bool_value';
        if (!empty($configuration['attributeValue']) && $configuration['attributeValue'] === 'id') {
            $selectColumn = 'id';
        }

        /** @var \Doctrine\DBAL\Connection $connection */
        $connection = $container->get('connection');

        $channelsIds = [''];
        if (!empty($configuration['channelId'])) {
            $channelsIds[] = $configuration['channelId'];
        }

        foreach ($channelsIds as $channelId) {
            $alias = "alias_{$configuration['id']}_{$channelId}";
            $qb1 = $connection->createQueryBuilder()
                ->select("$alias.$selectColumn")
                ->from('product_attribute_value', $alias)
                ->where("$alias.attribute_id = :{$alias}_attributeId")
                ->andWhere("$alias.deleted = :false")
                ->andWhere("$alias.channel_id = :{$alias}_channelId")
                ->andWhere("$alias.language = :{$alias}_language")
                ->andWhere("$alias.product_id =$mtAlias.id")
                ->setParameter("{$alias}_attributeId", $configuration['attributeId'])
                ->setParameter("{$alias}_channelId", $channelId)
                ->setParameter("{$alias}_language", $configuration['language'])
                ->setParameter("false", false, ParameterType::BOOLEAN);

            $qb->addSelect("({$qb1->getSQL()}) AS {$configuration['id']}_{$channelId}");
            foreach ($qb1->getParameters() as $pName => $pValue) {
                $qb->setParameter($pName, $pValue, $mapper::getParameterType($pValue));
            }
        }
    }

    public function convertToString(array &$result, array $record, array $configuration): void
    {
        $field = $configuration['field'];
        $column = $configuration['column'];

        if($record[$field] === null){
            $result[$column] = $configuration['nullValue'];
        }else{
            $result[$column] = !empty($record[$field]) ? 'TRUE' : 'FALSE';
        }
    }
}
