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

class V1Dot10Dot14 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2026-03-12 15:00:00');
    }

    public function up(): void
    {
        $this->exec("ALTER TABLE action ADD COLUMN export_feed_id VARCHAR(36) DEFAULT NULL");
        $this->exec("CREATE INDEX IDX_ACTION_EXPORT_FEED_ID ON action (export_feed_id, deleted)");

        $rows = $this->getDbal()->createQueryBuilder()
            ->select('id', 'data')
            ->from($this->getDbal()->quoteIdentifier('action'))
            ->where('data IS NOT NULL')
            ->fetchAllAssociative();

        foreach ($rows as $row) {
            $data = json_decode($row['data'], true);
            if (!is_array($data)) {
                continue;
            }

            $exportFeedId = $data['field']['exportFeedId'] ?? null;
            $hasName = isset($data['field']['exportFeedName']);

            if (!$exportFeedId && !$hasName) {
                continue;
            }

            unset($data['field']['exportFeedId'], $data['field']['exportFeedName']);

            $qb = $this->getDbal()->createQueryBuilder()
                ->update($this->getDbal()->quoteIdentifier('action'))
                ->set('data', ':data')
                ->setParameter('data', json_encode($data))
                ->where('id = :id')
                ->setParameter('id', $row['id']);

            if ($exportFeedId) {
                $qb->set('export_feed_id', ':val')
                    ->setParameter('val', $exportFeedId);
            }

            $qb->executeStatement();
        }
    }

    protected function exec(string $sql): void
    {
        try {
            $this->getPDO()->exec($sql);
        } catch (\Throwable $e) {
        }
    }
}