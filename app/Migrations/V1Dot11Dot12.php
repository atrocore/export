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

class V1Dot11Dot12 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2026-09-10 10:00:00');
    }

    public function up(): void
    {
        $rows = $this->getDbal()->createQueryBuilder()
            ->select('id', 'data')
            ->from($this->getDbal()->quoteIdentifier('action'))
            ->where('type = :type')
            ->andWhere('export_feed_id IS NULL')
            ->andWhere('data IS NOT NULL')
            ->setParameter('type', 'export')
            ->fetchAllAssociative();

        foreach ($rows as $row) {
            $data = json_decode($row['data'], true);
            if (!is_array($data) || empty($data['field']) || !is_array($data['field'])) {
                continue;
            }

            if (!array_key_exists('exportFeedId', $data['field'])) {
                continue;
            }

            $exportFeedId = $data['field']['exportFeedId'] ?? null;

            unset($data['field']['exportFeedId'], $data['field']['exportFeedName']);

            $qb = $this->getDbal()->createQueryBuilder()
                ->update($this->getDbal()->quoteIdentifier('action'))
                ->set('data', ':data')
                ->setParameter('data', json_encode($data))
                ->where('id = :id')
                ->setParameter('id', $row['id']);

            if (!empty($exportFeedId)) {
                $qb->set('export_feed_id', ':val')
                    ->setParameter('val', $exportFeedId);
            }

            $qb->executeStatement();
        }
    }
}
