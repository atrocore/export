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

declare(strict_types=1);

namespace Export\Migrations;

use Atro\Core\Exceptions\Error;
use Atro\Core\Migration\Base;

class V1Dot4Dot43 extends Base
{
    /**
     * @inheritDoc
     */
    public function up(): void
    {
        try {
            $items = $this
                ->getPDO()
                ->query("SELECT * FROM `export_configurator_item` WHERE deleted=0 AND value_modifier IS NOT NULL")
                ->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            $items = [];
        }

        foreach ($items as $item) {
            if (!empty($item['value_modifier'])) {
                $valueModifiers = [];
                foreach (explode('||', (string)$item['value_modifier']) as $valueModifier) {
                    if (!empty($valueModifier)) {
                        $valueModifiers[] = $valueModifier;
                    }
                }
                $this->execute("UPDATE `export_configurator_item` SET value_modifier='" . json_encode($valueModifiers) . "' WHERE id='{$item['id']}'");
            }
        }
    }

    /**
     * @inheritDoc
     */
    public function down(): void
    {
        throw new Error('Downgrade is prohibited!');
    }

    protected function execute(string $query): void
    {
        try {
            $this->getPDO()->exec($query);
        } catch (\Throwable $e) {
        }
    }
}
