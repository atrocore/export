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

use Atro\ORM\DB\RDB\Mapper;
use Doctrine\DBAL\Query\QueryBuilder;
use Espo\Core\SelectManagers\Base;
use Espo\ORM\IEntity;

class ExportJob extends Base
{
    protected function boolFilterOnlyExportFailed24Hours(array &$result): void
    {
        $result['callbacks'][] = [$this, 'getFailedExportJobFiltered24Hours'];
    }

    protected function boolFilterOnlyExportFailed7Days(array &$result): void
    {
        $result['callbacks'][] = [$this, 'getFailedExportJobFiltered7Days'];
    }

    protected function boolFilterOnlyExportFailed28Days(array &$result): void
    {
        $result['callbacks'][] = [$this, 'getFailedExportJobFiltered28Days'];
    }

    protected function getFailedExportJobFilteredIds(int $interval): QueryBuilder
    {
        $connection = $this->getEntityManager()->getConnection();

        return $connection->createQueryBuilder()
            ->select('t.id')
            ->from($connection->quoteIdentifier('export_job'), 't')
            ->where('t.state = :state')
            ->andWhere('t.start >= :start')
            ->setParameter('state', 'Failed')
            ->setParameter('start', (new \DateTime())->modify("-{$interval} days")->format('Y-m-d H:i:s'));
    }

    protected function getFailedExportJobFiltered(QueryBuilder $qb, string $tableAlias, int $interval): void
    {
        $qb1 = $this->getFailedExportJobFilteredIds($interval);

        $qb->andWhere("{$tableAlias}.id IN ({$qb1})");
        foreach ($qb1->getParameters() as $param => $val) {
            $qb->setParameter($param, $val, Mapper::getParameterType($val));
        }
    }

    public function getFailedExportJobFiltered24Hours(QueryBuilder $qb, IEntity $relEntity, array $params, Mapper $mapper): void
    {
        $this->getFailedExportJobFiltered($qb, $mapper->getQueryConverter()->getMainTableAlias(), 1);
    }

    public function getFailedExportJobFiltered7Days(QueryBuilder $qb, IEntity $relEntity, array $params, Mapper $mapper): void
    {
        $this->getFailedExportJobFiltered($qb, $mapper->getQueryConverter()->getMainTableAlias(), 7);
    }

    public function getFailedExportJobFiltered28Days(QueryBuilder $qb, IEntity $relEntity, array $params, Mapper $mapper): void
    {
        $this->getFailedExportJobFiltered($qb, $mapper->getQueryConverter()->getMainTableAlias(), 28);
    }
}
