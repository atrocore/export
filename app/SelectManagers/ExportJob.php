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

namespace Export\SelectManagers;

use Espo\Core\SelectManagers\Base;

class ExportJob extends Base
{
    protected function access(&$result)
    {
        $repository = $this->getEntityManager()->getRepository('ExportFeed');

        $sp = $this->createSelectManager('ExportFeed')->getSelectParams([], true, true);
        $sp['select'] = ['id'];

        $qb = $repository->getMapper()->createSelectQueryBuilder($repository->get(), $sp);

        $mainTableAlias = $this->getRepository()->getMapper()->getQueryConverter()->getMainTableAlias();
        $innerSql = str_replace($mainTableAlias, "t_ej", $qb->getSql());

        $where = [
            'innerSql' => [
                "sql"        => "$mainTableAlias.export_feed_id IN ({$innerSql})",
                "parameters" => $qb->getParameters()
            ]
        ];

        $result['whereClause'][] = ['OR' => $where];
    }

    protected function boolFilterOnlyExportFailed24Hours(array &$result): void
    {
        $result['whereClause'][] = [
            'id' => $this->getFailedExportJobFilteredIds(1)
        ];
    }

    protected function boolFilterOnlyExportFailed7Days(array &$result): void
    {
        $result['whereClause'][] = [
            'id' => $this->getFailedExportJobFilteredIds(7)
        ];
    }

    protected function boolFilterOnlyExportFailed28Days(array &$result): void
    {
        $result['whereClause'][] = [
            'id' => $this->getFailedExportJobFilteredIds(28)
        ];
    }

    protected function getFailedExportJobFilteredIds(int $interval): array
    {
        $connection = $this->getEntityManager()->getConnection();

        $res = $connection->createQueryBuilder()
            ->select('t.id')
            ->from($connection->quoteIdentifier('export_job'), 't')
            ->where('t.state = :state')
            ->andWhere('t.start >= :start')
            ->setParameter('state', 'Failed')
            ->setParameter('start', (new \DateTime())->modify("-{$interval} days")->format('Y-m-d H:i:s'))
            ->fetchAllAssociative();

        return array_column($res, 'id');
    }
}
