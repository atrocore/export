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

class V2Dot0Dot1 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2026-11-27 18:00:00');
    }

    public function up(): void
    {
        $fromSchema = $this->getCurrentSchema();
        if (!$fromSchema->hasTable('export_feed')) {
            return;
        }

        $toSchema = clone $fromSchema;

        $table = $toSchema->getTable('export_feed');
        if (!$table->hasColumn('disable_notifications')) {
            $table->addColumn('disable_notifications', 'boolean', ['notnull' => true, 'default' => false]);
        }

        foreach ($this->schemasDiffToSql($fromSchema, $toSchema) as $sql) {
            $this->getDbal()->executeStatement($sql);
        }
    }
}
