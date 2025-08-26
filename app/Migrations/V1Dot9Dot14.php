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
use Atro\Core\Utils\Util;
use Doctrine\DBAL\ParameterType;

class V1Dot9Dot14 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2025-08-26 11:00:00');
    }

    public function up(): void
    {
            $sortOrderRelation = [
                "1" => "ASC",
                "2" => "DESC"
            ];

        foreach ($sortOrderRelation as $old => $new) {
            $this->getConnection()->createQueryBuilder()
                ->update('export_configurator_item')
                ->set('sort_order_relation', ':new')
                ->where('sort_order_relation = :old')
                ->setParameter('new', $new)
                ->setParameter('old', $old)
                ->executeQuery();
        }
    }
}
