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

namespace Export\Migrations;

use Atro\Core\Migration\Base;
use Atro\ORM\DB\RDB\Mapper;
use Doctrine\DBAL\ParameterType;

class V1Dot9Dot11 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2025-08-08 18:00:00');
    }

    public function up(): void
    {
        try {
            $connection = $this->getConnection();

            $exportFeedIds = $connection
                ->createQueryBuilder()
                ->select('id')
                ->from('export_feed')
                ->where('data LIKE :data')
                ->setParameter('data', "%\"entity\":\"Product\"%")
                ->fetchAllAssociative();

            if (!empty($exportFeedIds)) {
                $exportFeedIds = array_column($exportFeedIds, 'id');

                $connection
                    ->createQueryBuilder()
                    ->update('export_configurator_item')
                    ->set('name', ':newName')
                    ->where('name = :oldName')
                    ->andWhere('export_feed_id IN (:exportFeedIds)')
                    ->andWhere('deleted = :false')
                    ->setParameter('newName', 'number')
                    ->setParameter('oldName', 'sku')
                    ->setParameter('exportFeedIds', $exportFeedIds, Mapper::getParameterType($exportFeedIds))
                    ->setParameter('false', false, ParameterType::BOOLEAN)
                    ->executeStatement();
            }
        } catch (\Throwable $e) {

        }
    }
}
