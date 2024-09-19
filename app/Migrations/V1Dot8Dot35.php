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

namespace Export\Migrations;

use Atro\Core\Migration\Base;
use Doctrine\DBAL\ParameterType;

class V1Dot8Dot35 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2024-09-20 09:00:00');
    }

    public function up(): void
    {
        if ($this->isPgSQL()) {
            $this->execute("ALTER TABLE export_feed ADD last_time TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL");
        } else {
            $this->execute("ALTER TABLE export_feed ADD last_time DATETIME DEFAULT NULL");
        }
        $this->execute("ALTER TABLE export_feed ADD last_status VARCHAR(255) DEFAULT NULL");

        $latestJobs = $this->getConnection()->createQueryBuilder()
            ->select('export_feed_id, MAX(start) AS start, state')
            ->from('export_job')
            ->where('deleted=:false')
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->groupBy('export_feed_id')
            ->addGroupBy('state')
            ->orderBy('start', 'DESC')
            ->fetchAllAssociative();

        $res = [];
        foreach ($latestJobs as $row) {
            if (!isset($res[$row['export_feed_id']])) {
                $res[$row['export_feed_id']] = $row;
            }
        }

        foreach ($res as $row) {
            $this->getConnection()->createQueryBuilder()
                ->update('export_feed')
                ->set('last_time', ':lastTime')
                ->set('last_status', ':lastStatus')
                ->where('id=:id')
                ->setParameter('lastTime', $row['start'])
                ->setParameter('lastStatus', $row['state'])
                ->setParameter('id', $row['export_feed_id'])
                ->executeQuery();
        }
    }

    public function down(): void
    {
    }

    protected function execute(string $query): void
    {
        try {
            $this->getPDO()->exec($query);
        } catch (\Throwable $e) {
        }
    }
}
