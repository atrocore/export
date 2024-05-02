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

class V1Dot8Dot6 extends Base
{
    public function up(): void
    {
        $fromSchema = $this->getCurrentSchema();
        $toSchema = clone $fromSchema;

        try {
            $this->addColumn($toSchema, 'sharing', 'export_feed_id', ['type' => 'varchar']);
            foreach ($this->schemasDiffToSql($fromSchema, $toSchema) as $sql) {
                $this->getPDO()->exec($sql);
            }
        } catch (\Throwable $e) {
        }
    }

    public function down(): void
    {
    }
}
