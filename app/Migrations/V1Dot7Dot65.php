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
use Atro\Core\Exceptions\Error;

class V1Dot7Dot65 extends Base
{
    public function up(): void
    {
        $fromSchema = $this->getCurrentSchema();
        $toSchema = clone $fromSchema;

        $this->addColumn($toSchema, 'export_feed', 'fallback_language', ['type' => 'varchar', 'default' => null]);
        $this->addColumn($toSchema, 'export_configurator_item', 'fallback_language', ['type' => 'varchar', 'default' => null]);

        foreach ($this->schemasDiffToSql($fromSchema, $toSchema) as $sql) {
            $this->execute($sql);
        }
    }

    public function down(): void
    {
        $fromSchema = $this->getCurrentSchema();
        $toSchema = clone $fromSchema;

        $this->dropColumn($toSchema, 'export_feed', 'fallback_language');
        $this->dropColumn($toSchema, 'export_configurator_item', 'fallback_language');

        foreach ($this->schemasDiffToSql($fromSchema, $toSchema) as $sql) {
            $this->execute($sql);
        }
    }

    protected function execute(string $sql)
    {
        try {
            $this->getPDO()->exec($sql);
        } catch (\Throwable $e) {
            // ignore all
        }
    }
}
