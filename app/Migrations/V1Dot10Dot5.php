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

class V1Dot10Dot5 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2025-11-04 08:00:00');
    }

    public function up(): void
    {
        $this->exec("ALTER TABLE file ADD export_job_id VARCHAR(36) DEFAULT NULL");
        $this->exec("CREATE INDEX IDX_FILE_EXPORT_JOB_ID ON file (export_job_id, deleted)");

        $offset = 0;
        $limit = 10000;

        while (true) {
            $res = $this->getConnection()->createQueryBuilder()
                ->select('*')
                ->from('export_job')
                ->where('file_id IS NOT NULL')
                ->setFirstResult($offset)
                ->setMaxResults($limit)
                ->fetchAllAssociative();

            $offset = $offset + $limit;

            if (empty($res)) {
                break;
            }

            foreach ($res as $row) {
                $this->getConnection()->createQueryBuilder()
                    ->update('file')
                    ->set('export_job_id', ':exportJobId')
                    ->where('id=:fileId')
                    ->setParameter('exportJobId', $row['id'])
                    ->setParameter('fileId', $row['file_id'])
                    ->executeQuery();
            }
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
