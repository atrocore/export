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
use Atro\Core\Utils\Util;
use Doctrine\DBAL\ParameterType;

class V1Dot9Dot1 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2025-05-23 15:00:00');
    }

    public function up(): void
    {
        try {
            $this->getConnection()->createQueryBuilder()
                ->update('export_configurator_item')
                ->set('entity_attribute_id', 'attribute_id')
                ->set('type', ':field')
                ->where('entity_attribute_id IS NULL AND attribute_id IS NOT NULL')
                ->andWhere('type = :type')
                ->setParameter('field', 'Field')
                ->setParameter('type', 'Attribute')
                ->executeStatement();

            $data = $this->getConnection()->createQueryBuilder()
                ->select('eci.*, a.code as attribute_code')
                ->from('export_configurator_item', 'eci')
                ->leftJoin('eci', 'attribute', 'a', 'eci.entity_attribute_id = a.id')
                ->where('eci.entity_attribute_id IS NOT NULL')
                ->andWhere('eci.language IS NOT NULL AND eci.deleted = :false')
                ->setParameter('false', false, ParameterType::BOOLEAN)
                ->fetchAllAssociative();

            foreach ($data as $row) {
                if(empty($row['language']) || $row['language'] === 'main') {
                    continue;
                }
                $name = $row['entity_attribute_id'];
                if(!empty($row['attribute_code'])) {
                    $name = preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/', $row['attribute_code']) === 1 ? $row['attribute_code'] : $name;
                }

                $name = $name.Util::toCamelCase(strtolower($row['language']), '_', true);
                $this->getConnection()->createQueryBuilder()
                    ->update('export_configurator_item')
                    ->set('name', ':name')
                    ->where('id = :id')
                    ->setParameter('name', $name)
                    ->setParameter('id', $row['id'])
                    ->executeStatement();
            }
        } catch (\Throwable $e) {
        }




        $this->exec("ALTER TABLE export_configurator_item DROP attribute_value");
//       $this->exec("ALTER TABLE export_configurator_item DROP \"language\"");
        $this->exec("ALTER TABLE export_configurator_item DROP fallback_language");
//      $this->exec("ALTER TABLE export_configurator_item DROP attribute_id");
        $this->exec("DROP INDEX idx_import_configurator_item_attribute_id;");
    }

    protected function exec(string $sql): void
    {
        try {
            $this->getPDO()->exec($sql);
        } catch (\Throwable $e) {
            // ignore all
        }
    }
}
