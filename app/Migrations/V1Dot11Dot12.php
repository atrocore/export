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

class V1Dot11Dot12 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2026-09-08 10:00:00');
    }

    public function up(): void
    {
        $this->addColumns();

        $this->migrateHeaderRowToNumberOfHeaders();
        $this->migrateColumnToDataField();
        $this->migrateVirtualFieldsToDataField();

        $this->dropColumns();
    }

    protected function addColumns(): void
    {
        $fromSchema = $this->getCurrentSchema();
        $toSchema = clone $fromSchema;

        if (!$toSchema->getTable('export_feed')->hasColumn('number_of_headers')) {
            $this->addColumn($toSchema, 'export_feed', 'number_of_headers', ['type' => 'int', 'default' => 1]);
        }
        if (!$toSchema->getTable('export_configurator_item')->hasColumn('data')) {
            $this->addColumn($toSchema, 'export_configurator_item', 'data', ['type' => 'jsonObject']);
        }

        foreach ($this->schemasDiffToSql($fromSchema, $toSchema) as $sql) {
            $this->getDbal()->executeStatement($sql);
        }
    }

    protected function dropColumns(): void
    {
        $fromSchema = $this->getCurrentSchema();
        $toSchema = clone $fromSchema;

        $table = $toSchema->getTable('export_configurator_item');
        foreach (['column', 'column_type', 'virtual_fields'] as $columnName) {
            if ($table->hasColumn($columnName)) {
                $this->dropColumn($toSchema, 'export_configurator_item', $columnName);
            }
        }

        foreach ($this->schemasDiffToSql($fromSchema, $toSchema) as $sql) {
            $this->getDbal()->executeStatement($sql);
        }
    }

    protected function migrateHeaderRowToNumberOfHeaders(): void
    {
        $exportFeeds = $this->getDbal()->createQueryBuilder()
            ->select('id', 'data')
            ->from('export_feed')
            ->where('deleted = :false')
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->fetchAllAssociative();

        foreach ($exportFeeds as $exportFeed) {
            $data = @json_decode((string)$exportFeed['data'], true);
            if (!is_array($data)) {
                $data = [];
            }

            // Already migrated (or never had the old flag to begin with) - skip, don't let a
            // re-run of this migration stomp on a numberOfHeaders value set since.
            if (!array_key_exists('isFileHeaderRow', $data)) {
                continue;
            }

            $wasHeaderRowEnabled = $data['isFileHeaderRow'];
            unset($data['isFileHeaderRow']);

            $this->getDbal()->createQueryBuilder()
                ->update('export_feed')
                ->set('number_of_headers', ':numberOfHeaders')
                ->set('data', ':data')
                ->where('id = :id')
                ->setParameter('numberOfHeaders', $wasHeaderRowEnabled ? 1 : 0, ParameterType::INTEGER)
                ->setParameter('data', json_encode($data))
                ->setParameter('id', $exportFeed['id'])
                ->executeQuery();
        }
    }

    /**
     * column/column_type ("header 1") become notStorable/dataField fields headerText1/
     * headerProperty1, packed into the new `data` column (matching every other dataField-backed
     * entity in this codebase, e.g. export_feed itself) instead of their own dedicated real columns.
     */
    protected function migrateColumnToDataField(): void
    {
        // "column" is a borderline-reserved word in both dialects - quote it defensively
        $columnIdentifier = $this->getDbal()->quoteIdentifier('column');

        $items = $this->getDbal()->createQueryBuilder()
            ->select("id, $columnIdentifier, column_type")
            ->from('export_configurator_item')
            ->where('deleted = :false')
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->fetchAllAssociative();

        foreach ($items as $item) {
            $data = [
                'headerText1'     => $item['column'],
                'headerProperty1' => $item['column_type'] ?? 'custom',
            ];

            $this->getDbal()->createQueryBuilder()
                ->update('export_configurator_item')
                ->set('data', ':data')
                ->where('id = :id')
                ->setParameter('data', json_encode($data))
                ->setParameter('id', $item['id'])
                ->executeQuery();
        }
    }

    /**
     * fileNameTemplate/exportStaticListLabel move from the custom virtual_fields column (a
     * hand-rolled duplicate of the dataField mechanism - see Export\Entities\ExportConfiguratorItem)
     * to plain notStorable/dataField fields, packed flat into the same `data` column as
     * headerText1/headerProperty1 above.
     */
    protected function migrateVirtualFieldsToDataField(): void
    {
        $items = $this->getDbal()->createQueryBuilder()
            ->select('id, virtual_fields, data')
            ->from('export_configurator_item')
            ->where('deleted = :false')
            ->andWhere('virtual_fields IS NOT NULL')
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->fetchAllAssociative();

        foreach ($items as $item) {
            $virtualFields = @json_decode((string)$item['virtual_fields'], true);
            if (!is_array($virtualFields) || empty($virtualFields)) {
                continue;
            }

            $data = @json_decode((string)$item['data'], true);
            if (!is_array($data)) {
                $data = [];
            }

            $data = array_merge($data, $virtualFields);

            $this->getDbal()->createQueryBuilder()
                ->update('export_configurator_item')
                ->set('data', ':data')
                ->where('id = :id')
                ->setParameter('data', json_encode($data))
                ->setParameter('id', $item['id'])
                ->executeQuery();
        }
    }
}
