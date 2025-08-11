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

class V1Dot9Dot12 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2025-08-11 18:00:00');
    }

    public function up(): void
    {
        $this->updateTemplate();
        $this->updateFileNameMask();
        $this->updateConfiguratorItems();
    }

    protected function updateTemplate(): void
    {
        $actions = $this
            ->getConnection()
            ->createQueryBuilder()
            ->select('id', 'template')
            ->from('export_feed')
            ->where('template IS NOT NULL')
            ->andWhere('deleted = :false')
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->fetchAllAssociative();

        foreach ($actions as $action) {
            if (strpos($action['template'], 'sku') !== false) {
                $action['template'] = str_replace('.sku', '.number', $action['template']);
                $action['template'] = str_replace('"sku"', '"number"', $action['template']);
                $action['template'] = str_replace("'sku'", "'number'", $action['template']);

                try {
                    $this
                        ->getConnection()
                        ->createQueryBuilder()
                        ->update('export_feed')
                        ->set('template', ':template')
                        ->where('id = :id')
                        ->setParameter('id', $action['id'])
                        ->setParameter('template', $action['template'])
                        ->executeStatement();
                } catch (\Throwable $e) {

                }
            }
        }
    }

    protected function updateFileNameMask(): void
    {
        $actions = $this
            ->getConnection()
            ->createQueryBuilder()
            ->select('id', 'file_name_mask')
            ->from('export_feed')
            ->where('file_name_mask IS NOT NULL')
            ->andWhere('deleted = :false')
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->fetchAllAssociative();

        foreach ($actions as $action) {
            if (strpos($action['file_name_mask'], 'sku') !== false) {
                $action['file_name_mask'] = str_replace('.sku', '.number', $action['file_name_mask']);
                $action['file_name_mask'] = str_replace('"sku"', '"number"', $action['file_name_mask']);
                $action['file_name_mask'] = str_replace("'sku'", "'number'", $action['file_name_mask']);

                try {
                    $this
                        ->getConnection()
                        ->createQueryBuilder()
                        ->update('export_feed')
                        ->set('file_name_mask', ':fileNameMask')
                        ->where('id = :id')
                        ->setParameter('id', $action['id'])
                        ->setParameter('fileNameMask', $action['file_name_mask'])
                        ->executeStatement();
                } catch (\Throwable $e) {

                }
            }
        }
    }

    protected function updateConfiguratorItems(): void
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
