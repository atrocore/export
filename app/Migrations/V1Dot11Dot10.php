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

use Atro\Core\Migration\Base;
use Export\Seeders\FailedExportTemplateSeeder;

class V1Dot11Dot10 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2026-08-03 18:00:00');
    }

    public function up(): void
    {
        (new FailedExportTemplateSeeder($this->getConfig(), $this->getDbal()))->run();
    }
}
