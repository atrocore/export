<?php
/*
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

class V1Dot10Dot12 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2026-01-30 18:10:00');
    }

    public function up(): void
    {
        $this->migrateConfiguratorItems();
        $this->migrateTemplates();
        $this->migrateFileNameMask();
        $this->migrateData();
    }

    protected function migrateConfiguratorItems(): void
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
                    ->setParameter('newName', 'status')
                    ->setParameter('oldName', 'productStatus')
                    ->setParameter('exportFeedIds', $exportFeedIds, Mapper::getParameterType($exportFeedIds))
                    ->setParameter('false', false, ParameterType::BOOLEAN)
                    ->executeStatement();
            }
        } catch (\Throwable $e) {

        }
    }

    protected function migrateTemplates(): void
    {
        $feeds = $this
            ->getConnection()
            ->createQueryBuilder()
            ->select('id', 'template')
            ->from('export_feed')
            ->where('template IS NOT NULL')
            ->andWhere('deleted = :false')
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->fetchAllAssociative();

        foreach ($feeds as $feed) {
            if (strpos($feed['template'], 'productStatus') !== false) {
                $feed['template'] = str_replace('.productStatus', '.status', $feed['template']);
                $feed['template'] = str_replace('"productStatus"', '"status"', $feed['template']);
                $feed['template'] = str_replace("'productStatus'", "'status'", $feed['template']);

                try {
                    $this
                        ->getConnection()
                        ->createQueryBuilder()
                        ->update('export_feed')
                        ->set('template', ':template')
                        ->where('id = :id')
                        ->setParameter('id', $feed['id'])
                        ->setParameter('template', $feed['template'])
                        ->executeStatement();
                } catch (\Throwable $e) {

                }
            }
        }
    }

    protected function migrateFileNameMask(): void
    {
        $feeds = $this
            ->getConnection()
            ->createQueryBuilder()
            ->select('id', 'file_name_mask')
            ->from('export_feed')
            ->where('file_name_mask IS NOT NULL')
            ->andWhere('deleted = :false')
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->fetchAllAssociative();

        foreach ($feeds as $feed) {
            if (strpos($feed['file_name_mask'], 'productStatus') !== false) {
                $feed['file_name_mask'] = str_replace('.productStatus', '.status', $feed['file_name_mask']);
                $feed['file_name_mask'] = str_replace('"productStatus"', '"status"', $feed['file_name_mask']);
                $feed['file_name_mask'] = str_replace("'productStatus'", "'status'", $feed['file_name_mask']);

                try {
                    $this
                        ->getConnection()
                        ->createQueryBuilder()
                        ->update('export_feed')
                        ->set('file_name_mask', ':fileNameMask')
                        ->where('id = :id')
                        ->setParameter('id', $feed['id'])
                        ->setParameter('fileNameMask', $feed['file_name_mask'])
                        ->executeStatement();
                } catch (\Throwable $e) {

                }
            }
        }
    }

    protected function migrateData(): void
    {
        $feeds = $this
            ->getConnection()
            ->createQueryBuilder()
            ->select('id', 'data')
            ->from('export_feed')
            ->where('data IS NOT NULL')
            ->andWhere('deleted = :false')
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->fetchAllAssociative();

        foreach ($feeds as $feed) {
            if (strpos($feed['data'], 'productStatus') !== false) {
                $feed['data'] = str_replace('.productStatus', '.status', $feed['data']);
                $feed['data'] = str_replace('"productStatus"', '"status"', $feed['data']);
                $feed['data'] = str_replace("'productStatus'", "'status'", $feed['data']);

                try {
                    $this
                        ->getConnection()
                        ->createQueryBuilder()
                        ->update('export_feed')
                        ->set('data', ':data')
                        ->where('id = :id')
                        ->setParameter('id', $feed['id'])
                        ->setParameter('data', $feed['data'])
                        ->executeStatement();
                } catch (\Throwable $e) {

                }
            }
        }
    }
}