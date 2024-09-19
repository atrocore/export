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
