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

use Atro\Core\SelectManagers\Base;

/**
 * Class ExportFeed
 */
class ExportFeed extends Base
{
    /**
     * @inheritdoc
     */
    public function getSelectParams(array $params, $withAcl = false, $checkWherePermission = false)
    {
        $exportTypes = [];
        foreach ($this->getMetadata()->get(['app', 'services'], []) as $serviceName => $serviceClassName) {
            if (strpos($serviceName, 'ExportType') !== false) {
                $exportTypes[] = lcfirst(str_replace('ExportType', '', $serviceName));
            }
        }

        // filtering by ExportFeed types
        $params['where'][] = [
            'type'      => 'in',
            'attribute' => 'type',
            'value'     => $exportTypes
        ];

        if (!empty($params['exportEntity'])) {
            $params['where'][] = [
                'type'      => 'equals',
                'attribute' => 'entity',
                'value'     => (string)$params['exportEntity']
            ];
        }

        return parent::getSelectParams($params, $withAcl, $checkWherePermission);
    }

    protected function getRepository(): \Export\Repositories\ExportFeed
    {
        return $this->getEntityManager()->getRepository('ExportFeed');
    }

    protected function boolFilterOnlyExportFailed24Hours(array &$result): void
    {
        $result['whereClause'][] = [
            'id' => $this->getExportFeedFilteredIds(1)
        ];
    }

    protected function boolFilterOnlyExportFailed7Days(array &$result): void
    {
        $result['whereClause'][] = [
            'id' => $this->getExportFeedFilteredIds(7)
        ];
    }

    protected function boolFilterOnlyExportFailed28Days(array &$result): void
    {
        $result['whereClause'][] = [
            'id' => $this->getExportFeedFilteredIds(28)
        ];
    }

    protected function boolFilterHasMultipleSheets(array &$result): void
    {
        $result['whereClause'][] = [
            'hasMultipleSheets' => true
        ];
    }

    protected function getExportFeedFilteredIds(int $interval): array
    {
        $connection = $this->getEntityManager()->getConnection();

        $res = $connection->createQueryBuilder()
            ->select('exp.id')
            ->from($connection->quoteIdentifier('export_feed'), 'exp')
            ->innerJoin('exp', $connection->quoteIdentifier('export_job'), 'exj', 'exj.export_feed_id = exp.id')
            ->where('exj.state = :state')
            ->andWhere('exj.start >= :start')
            ->setParameter('state', 'Failed')
            ->setParameter('start', (new \DateTime())->modify("-{$interval} days")->format('Y-m-d H:i:s'))
            ->fetchAllAssociative();

        return array_column($res, 'id');
    }
}
