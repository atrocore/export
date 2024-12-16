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

class V1Dot8Dot58 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2024-12-16 14:00:00');
    }

    public function up(): void
    {
        $this->getConnection()->createQueryBuilder()
            ->delete('scheduled_job')
            ->where('type=:type')
            ->setParameter('type', 'ExportJobRemove')
            ->executeQuery();

        $this->getConnection()->createQueryBuilder()
            ->delete($this->getConnection()->quoteIdentifier('job'))
            ->where('type=:type')
            ->setParameter('type', 'ExportJobRemove')
            ->executeQuery();
    }
}
