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
        $this->getConnection()->createQueryBuilder()
            ->delete('export_configurator_item')
            ->where('deleted=:true')
            ->setParameter('true', true, ParameterType::BOOLEAN)
            ->executeQuery();

        $this->getConnection()->createQueryBuilder()
            ->update('export_configurator_item')
            ->set('name', ':null')
            ->where('type!=:fieldType')
            ->setParameter('null', null, ParameterType::NULL)
            ->setParameter('fieldType', 'Field')
            ->executeQuery();

        $res = $this->getConnection()->createQueryBuilder()
            ->select('*')
            ->from('export_configurator_item')
            ->where('type=:fieldType')
            ->setParameter('fieldType', 'Field')
            ->fetchAllAssociative();

        foreach ($res as $row) {
            if (empty($row['export_feed_id'])) {
                continue;
            }

            $this->getConnection()->createQueryBuilder()
                ->delete('export_configurator_item')
                ->where('export_feed_id=:exportFeedId')
                ->andWhere('name=:name')
                ->andWhere('id!=:id')
                ->setParameter('exportFeedId', $row['export_feed_id'])
                ->setParameter('name', $row['name'])
                ->setParameter('id', $row['id'])
                ->executeQuery();
        }

        $this->exec("CREATE UNIQUE INDEX IDX_EXPORT_CONFIGURATOR_ITEM_UNIQUE_FIELD ON export_configurator_item (deleted, name, export_feed_id)");

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
