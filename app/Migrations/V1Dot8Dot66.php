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

class V1Dot8Dot66 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2025-01-14 18:00:00');
    }

    public function up(): void
    {
        // ALTER TABLE export_feed ADD locale_id VARCHAR(36) DEFAULT NULL;
        // ALTER TABLE export_feed DROP language;
        // ALTER TABLE export_feed DROP fallback_language;
        // CREATE INDEX IDX_EXPORT_FEED_LOCALE_ID ON export_feed (locale_id, deleted)

        // create new locale according to feeds configurations
        // thousandSeparator
        // decimalMark
        // language
        // fallbackLanguage
    }
}
