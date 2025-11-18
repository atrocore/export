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

class V1Dot10Dot6 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2025-11-17 16:00:00');
    }

    public function up(): void
    {
        if($this->isPgSQL()) {
            $this->exec("ALTER TABLE export_configurator_item ADD search_filter TEXT DEFAULT NULL");
            $this->exec("COMMENT ON COLUMN export_configurator_item.search_filter IS '(DC2Type:jsonObject)'");
        }else{
            $this->exec("ALTER TABLE export_configurator_item ADD search_filter LONGTEXT DEFAULT NULL COMMENT '(DC2Type:jsonObject)'");
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
