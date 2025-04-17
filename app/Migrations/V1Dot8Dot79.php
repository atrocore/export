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

class V1Dot8Dot79 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2024-04-16 15:00:00');
    }

    public function up(): void
    {
        $this->exec("ALTER TABLE export_configurator_item ADD entity_attribute_id VARCHAR(36) DEFAULT NULL");
        $this->exec("CREATE INDEX IDX_EXPORT_CONFIGURATOR_ITEM_ENTITY_ATTRIBUTE_ID ON export_configurator_item (entity_attribute_id, deleted)");
    }

    protected function exec(string $query): void
    {
        try {
            $this->getPDO()->exec($query);
        } catch (\Throwable $e) {
        }
    }
}
