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
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Schema\Schema;
use Ramsey\Uuid\Uuid;

class V2Dot0Dot0 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2026-10-30 13:00:00');
    }

    public function up(): void
    {
        $this->migrateAction();
        $this->migrateExportFeed();
    }

    /**
     * The export feed types are subtypes of ExportFeed now. The settings of the file format and the entity, stored in
     * the "data" field before, are columns of the export feed. The settings of the type are moved to the table of the
     * subtype, the subtypes of the other modules are created by their migrations.
     */
    protected function migrateExportFeed(): void
    {
        $formatColumns = [
            'entity' => ['entity', 'type' => 'string', 'length' => 255, 'default' => 'Product'],
            'csvFieldDelimiter' => ['csv_field_delimiter', 'type' => 'string', 'length' => 255, 'default' => ';'],
            'csvTextQualifier' => ['csv_text_qualifier', 'type' => 'string', 'length' => 255, 'default' => 'doubleQuote'],
            'convertCollectionToString' => ['convert_collection_to_string', 'type' => 'boolean', 'notnull' => true, 'default' => true],
            'delimiter' => ['delimiter', 'type' => 'string', 'length' => 255, 'default' => '~'],
            'convertRelationsToString' => ['convert_relations_to_string', 'type' => 'boolean', 'notnull' => true, 'default' => true],
            'fieldDelimiterForRelation' => ['field_delimiter_for_relation', 'type' => 'string', 'length' => 255, 'default' => '|'],
            'emptyValue' => ['empty_value', 'type' => 'string', 'length' => 255, 'default' => ''],
            'nullValue' => ['null_value', 'type' => 'string', 'length' => 255, 'default' => 'Null'],
            'markForNoRelation' => ['mark_for_no_relation', 'type' => 'string', 'length' => 255, 'default' => 'Null'],
            'markForUnlinkedAttribute' => ['mark_for_unlinked_attribute', 'type' => 'string', 'length' => 255, 'default' => 'N/A'],
            'exportByMaxDepth' => ['export_by_max_depth', 'type' => 'string', 'length' => 255, 'default' => '1'],
        ];

        $subtypes = [
            'simple' => [
                'fileNameMask' => ['file_name_mask', 'type' => 'text', 'source' => 'column'],
            ],
        ];

        $fromSchema = $this->getCurrentSchema();
        if (!$fromSchema->hasTable('export_feed')) {
            return;
        }

        $toSchema = clone $fromSchema;

        $table = $toSchema->getTable('export_feed');
        foreach ($formatColumns as $options) {
            $column = array_shift($options);
            $type = $options['type'];
            unset($options['type']);
            if (!$table->hasColumn($column)) {
                $table->addColumn($column, $type, array_merge(['notnull' => false], $options));
            }
        }

        foreach ($subtypes as $type => $columns) {
            $this->createSubtypeTable($toSchema, 'export_feed', $type, $columns);
        }

        foreach ($this->schemasDiffToSql($fromSchema, $toSchema) as $sql) {
            $this->getDbal()->executeStatement($sql);
        }

        $records = $this->getDbal()->createQueryBuilder()
            ->select('*')
            ->from('export_feed')
            ->fetchAllAssociative();

        foreach ($records as $record) {
            $this->updateFormatColumns($record, $formatColumns);

            if (isset($subtypes[$record['type']])) {
                $columns = $subtypes[$record['type']];
                $this->insertSubtypeRecord('export_feed', $record['type'], $record['id'], $columns, $this->getFeedSettings($record, $columns));
            }
        }
    }

    /**
     * The settings of the file format were stored in the "feedFields" of the "data" field.
     */
    protected function updateFormatColumns(array $record, array $formatColumns): void
    {
        $data = @json_decode((string)($record['data'] ?? ''), true);
        $feedFields = is_array($data['feedFields'] ?? null) ? $data['feedFields'] : [];

        $qb = $this->getDbal()->createQueryBuilder()
            ->update('export_feed')
            ->where('id = :id')
            ->setParameter('id', $record['id']);

        $hasValues = false;
        foreach ($formatColumns as $field => $options) {
            if (!array_key_exists($field, $feedFields) || $feedFields[$field] === null) {
                continue;
            }

            $qb->set($this->getDbal()->quoteIdentifier($options[0]), ":$field");
            if ($options['type'] === 'boolean') {
                $qb->setParameter($field, !empty($feedFields[$field]), ParameterType::BOOLEAN);
            } else {
                $qb->setParameter($field, (string)$feedFields[$field]);
            }
            $hasValues = true;
        }

        if ($hasValues) {
            $qb->executeStatement();
        }
    }

    /**
     * The settings of an export feed type: a setting was stored in a column of the export feed table or in the
     * "feedFields" of the "data" field.
     */
    protected function getFeedSettings(array $record, array $columns): array
    {
        $data = @json_decode((string)($record['data'] ?? ''), true);
        $feedFields = is_array($data['feedFields'] ?? null) ? $data['feedFields'] : [];

        $settings = [];
        foreach ($columns as $field => $options) {
            $settings[$field] = $options['source'] === 'data' ? ($feedFields[$field] ?? null) : ($record[$options[0]] ?? null);
        }

        return $settings;
    }

    /**
     * The action types of the module are subtypes of Action now: the settings of the type are moved from the columns
     * of the action table and from its "data" field to the table of the subtype.
     */
    protected function migrateAction(): void
    {
        $subtypes = [
            'export' => [
                'exportFeedId' => ['export_feed_id', 'type' => 'string', 'length' => 36, 'index' => true, 'source' => 'column'],
                'contentLanguageId' => ['content_language_id', 'type' => 'string', 'length' => 36, 'source' => 'column'],
                'localeId' => ['locale_id', 'type' => 'string', 'length' => 36, 'source' => 'column'],
                'payload' => ['payload', 'type' => 'text', 'source' => 'data'],
            ],
            'reExportFailedJob' => [
                'applyToPreselectedRecords' => ['apply_to_preselected_records', 'type' => 'boolean', 'notnull' => true, 'default' => false, 'source' => 'data'],
            ],
        ];

        $fromSchema = $this->getCurrentSchema();
        $toSchema = clone $fromSchema;

        foreach ($subtypes as $type => $columns) {
            $this->createSubtypeTable($toSchema, 'action', $type, $columns);
        }

        foreach ($this->schemasDiffToSql($fromSchema, $toSchema) as $sql) {
            $this->getDbal()->executeStatement($sql);
        }

        if (!$fromSchema->hasTable('action')) {
            return;
        }

        $records = $this->getDbal()->createQueryBuilder()
            ->select('*')
            ->from($this->getDbal()->quoteIdentifier('action'))
            ->where('type IN (:types)')
            ->setParameter('types', array_keys($subtypes), Connection::PARAM_STR_ARRAY)
            ->fetchAllAssociative();

        foreach ($records as $record) {
            $columns = $subtypes[$record['type']];
            $this->insertSubtypeRecord('action', $record['type'], $record['id'], $columns, $this->getActionSettings($record, $columns));
        }
    }

    /**
     * The settings of an action: a setting was stored in a column of the action table or in the "data" field.
     */
    protected function getActionSettings(array $record, array $columns): array
    {
        $data = @json_decode((string)($record['data'] ?? ''), true);
        $virtualFields = is_array($data['field'] ?? null) ? $data['field'] : [];

        $settings = [];
        foreach ($columns as $field => $options) {
            $settings[$field] = $options['source'] === 'data' ? ($virtualFields[$field] ?? null) : ($record[$options[0]] ?? null);
        }

        return $settings;
    }

    /**
     * The table of a subtype: its own id, the unique link to the supertype record that is removed together with
     * it, the columns of the subtype fields.
     */
    protected function createSubtypeTable(Schema $schema, string $supertypeTable, string $type, array $columns): void
    {
        $tableName = $this->getSubtypeTableName($supertypeTable, $type);
        if ($schema->hasTable($tableName)) {
            return;
        }

        $table = $schema->createTable($tableName);
        $table->addColumn('id', 'string', ['length' => 36, 'notnull' => true]);
        $table->addColumn('supertype_id', 'string', ['length' => 36, 'notnull' => false]);

        $indexes = [];
        foreach ($columns as $options) {
            $column = array_shift($options);
            $type = $options['type'];
            if (!empty($options['index'])) {
                $indexes[] = $column;
            }
            unset($options['type'], $options['index'], $options['source']);
            $table->addColumn($column, $type, array_merge(['notnull' => false], $options));
        }

        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['supertype_id']);

        $foreignKeyName = $this->generateForeignKeyName($tableName . '_supertype_id');
        $table->addForeignKeyConstraint($supertypeTable, ['supertype_id'], ['id'], ['onDelete' => 'CASCADE'], $foreignKeyName);
        $table->addIndex(['supertype_id'], $foreignKeyName);

        foreach ($indexes as $column) {
            $table->addIndex([$column], $this->generateIndexName($tableName . '_' . $column));
        }
    }

    protected function insertSubtypeRecord(string $supertypeTable, string $type, string $supertypeId, array $columns, array $data): void
    {
        $tableName = $this->getSubtypeTableName($supertypeTable, $type);
        $conn = $this->getDbal();

        $exists = $conn->createQueryBuilder()
            ->select('id')
            ->from($tableName)
            ->where('supertype_id = :supertypeId')
            ->setParameter('supertypeId', $supertypeId)
            ->fetchOne();

        if ($exists !== false) {
            return;
        }

        $qb = $conn->createQueryBuilder()
            ->insert($tableName)
            ->setValue('id', ':id')
            ->setValue('supertype_id', ':supertypeId')
            ->setParameter('id', Uuid::uuid7()->toString())
            ->setParameter('supertypeId', $supertypeId);

        foreach ($columns as $field => $options) {
            if (!array_key_exists($field, $data) || $data[$field] === null) {
                continue;
            }

            $value = $data[$field];
            $parameterType = ParameterType::STRING;

            switch ($options['type']) {
                case 'boolean':
                    $value = !empty($value);
                    $parameterType = ParameterType::BOOLEAN;
                    break;
                case 'integer':
                    if ($value === '') {
                        continue 2;
                    }
                    $value = (int)$value;
                    $parameterType = ParameterType::INTEGER;
                    break;
                case 'float':
                    if ($value === '') {
                        continue 2;
                    }
                    $value = (string)(float)$value;
                    break;
                case 'jsonArray':
                    $value = json_encode($value);
                    break;
                default:
                    $value = is_scalar($value) ? (string)$value : json_encode($value);
            }

            $qb->setValue($conn->quoteIdentifier($options[0]), ":$field");
            $qb->setParameter($field, $value, $parameterType);
        }

        $qb->executeStatement();
    }

    protected function getSubtypeTableName(string $supertypeTable, string $type): string
    {
        return $supertypeTable . '_type_' . strtolower(preg_replace('/([A-Z])/', '_$1', $type));
    }

    protected function generateIndexName(string $name): string
    {
        if (strlen($name) > 55) {
            $name = md5($name);
        }

        return 'IDX_' . strtoupper($name);
    }

    protected function generateForeignKeyName(string $name): string
    {
        if (strlen($name) > 55) {
            $name = md5($name);
        }

        return 'FK_' . strtoupper($name);
    }
}
