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

class V1Dot10Dot2 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2025-09-17 18:00:00');
    }

    public function up(): void
    {
        $this->exec("ALTER TABLE export_feed ADD max_workers INT DEFAULT NULL");

        $this->getConnection()->createQueryBuilder()
            ->update('export_feed')
            ->set('sort_order_direction', ':null')
            ->where('sort_order_direction = :empty')
            ->setParameter('null', null, ParameterType::NULL)
            ->setParameter('empty', '')
            ->executeQuery();
    }

    protected function exec(string $sql): void
    {
        try {
            $this->getPDO()->exec($sql);
        } catch (\Throwable $e) {
        }
    }
}
