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

class V1Dot9Dot15 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2026-06-22 10:00:00');
    }

    public function up(): void
    {
        $conn = $this->getDbal();

        $items = $conn->createQueryBuilder()
            ->select('eci.id', 'eci.name', 'eci.entity_attribute_id')
            ->from('export_configurator_item', 'eci')
            ->where('eci.entity_attribute_id IS NOT NULL')
            ->andWhere('eci.deleted = :false')
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->fetchAllAssociative();

        foreach ($items as $item) {
            $attributeId = $item['entity_attribute_id'];
            $name        = $item['name'];

            $attribute = $conn->createQueryBuilder()
                ->select('a.system_name')
                ->from('attribute', 'a')
                ->where('a.id = :id')
                ->setParameter('id', $attributeId)
                ->fetchAssociative();

            if (empty($attribute)) {
                continue;
            }

            $systemName = $attribute['system_name'] ?? null;

            if (empty($systemName) || $systemName === $attributeId || !str_starts_with($name, $attributeId)) {
                continue;
            }

            $newName = $systemName . substr($name, strlen($attributeId));

            $conn->createQueryBuilder()
                ->update('export_configurator_item')
                ->set('name', ':name')
                ->where('id = :id')
                ->setParameter('name', $newName)
                ->setParameter('id', $item['id'])
                ->executeQuery();
        }
    }
}
