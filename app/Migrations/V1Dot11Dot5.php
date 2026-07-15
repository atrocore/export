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
use Doctrine\DBAL\ParameterType;

class V1Dot11Dot5 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2026-07-15 10:00:00');
    }

    public function up(): void
    {
        $this->exec("ALTER TABLE export_feed ADD content_language_id VARCHAR(36) DEFAULT NULL");
        $this->exec("CREATE INDEX IDX_EXPORT_FEED_CONTENT_LANGUAGE_ID ON export_feed (content_language_id, deleted)");

        $mainLanguageId = $this->resolveMainLanguageId();
        if ($mainLanguageId !== null) {
            $this->getDbal()
                ->createQueryBuilder()
                ->update('export_feed')
                ->set('content_language_id', ':mainId')
                ->where('deleted = :deleted')
                ->setParameter('mainId', $mainLanguageId)
                ->setParameter('deleted', false, ParameterType::BOOLEAN)
                ->executeQuery();
        }

        $this->exec("ALTER TABLE action ADD locale_id VARCHAR(36) DEFAULT NULL");
        $this->exec("CREATE INDEX IDX_ACTION_LOCALE_ID ON action (locale_id, deleted)");

        if ($this->isPgSQL()) {
            $this->exec("ALTER TABLE export_configurator_item ADD selected_language_only BOOLEAN DEFAULT 'false' NOT NULL");
        } else {
            $this->exec("ALTER TABLE export_configurator_item ADD selected_language_only TINYINT(1) DEFAULT '0' NOT NULL");
        }
    }

    private function resolveMainLanguageId(): ?string
    {
        $path = 'data/reference-data/Language.json';
        $content = @file_get_contents($path);
        if ($content === false) {
            return null;
        }
        $languages = json_decode($content, true);
        if (!is_array($languages)) {
            return null;
        }
        foreach ($languages as $lang) {
            if (($lang['role'] ?? '') === 'main') {
                return $lang['id'];
            }
        }
        return null;
    }

    protected function exec(string $sql): void
    {
        try {
            $this->getPDO()->exec($sql);
        } catch (\Throwable $e) {
        }
    }
}
