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
use Atro\Core\Utils\Util;
use Atro\Core\Migration\Base;

class V1Dot6Dot20 extends Base
{
    public function up(): void
    {
        $this->getPDO()->exec("UPDATE export_feed SET `language`='' WHERE `language`='mainLocale'");
        $this->exec("UPDATE export_configurator_item SET locale='main' WHERE locale='' OR locale IS NULL OR locale='mainLocale'");
        $this->exec("ALTER TABLE export_configurator_item CHANGE locale `language` VARCHAR(255) DEFAULT NULL COLLATE `utf8mb4_unicode_ci`");

        $records = $this->getPDO()->query(
            "SELECT eci.*, ef.data as ef_data FROM export_configurator_item eci INNER JOIN export_feed ef ON ef.id=eci.export_feed_id WHERE eci.deleted=0 AND ef.deleted=0 AND eci.type='Field'"
        )->fetchAll(\PDO::FETCH_ASSOC);

        $languages = [];
        if ($this->getConfig()->get('isMultilangActive')) {
            $languages = $this->getConfig()->get('inputLanguageList', []);
        }

        foreach ($languages as $language) {
            foreach ($records as $record) {
                if (empty($record['name']) || !is_string($record['name']) || mb_strlen($record['name']) < 5) {
                    continue;
                }

                $suffix = ucfirst(Util::toCamelCase(strtolower($language)));

                if ($suffix === mb_substr($record['name'], -4)) {
                    $name = $this->getPDO()->quote(mb_substr($record['name'], 0, -4));
                    $this->getPDO()->exec("UPDATE export_configurator_item SET `name`=$name, `language`='{$language}' WHERE id='{$record['id']}'");
                }
            }
        }
    }

    public function down(): void
    {
        throw new Error('Downgrade is prohibited!');
    }

    protected function exec(string $query): void
    {
        try {
            $this->getPDO()->exec($query);
        } catch (\Throwable $e) {
        }
    }
}
