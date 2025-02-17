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

class V1Dot8Dot72 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2025-02-14 15:00:00');
    }

    public function up(): void
    {
        if($this->isPgSQL()) {
            $this->exec("ALTER TABLE export_feed ADD use_quote_for_all BOOLEAN DEFAULT 'false' NOT NULL");
        } else {
            $this->exec("ALTER TABLE export_feed ADD use_quote_for_all TINYINT(1) DEFAULT '0' NOT NULL");
        }
    }

    protected function exec(string $query): void
    {
        try {
            $this->getPDO()->exec($query);
        } catch (\Throwable $e) {
        }
    }
}
