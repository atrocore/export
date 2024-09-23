<?php
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

declare(strict_types=1);

namespace Export\FieldConverters;

use AdvancedDataTypes\Core\Utils\MigrationHelper;
use Atro\Core\Container;
use Atro\Core\Exceptions\Error;
use Atro\Core\Utils\Database\DBAL\Schema\Converter;
use Atro\ORM\DB\RDB\Mapper;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Query\QueryBuilder;
use Espo\Core\Utils\Util;
use Espo\ORM\EntityCollection;
use Pim\Entities\Attribute;

class LinkMultipleType extends LinkType
{
    public function convertToString(array &$result, array $record, array $configuration): void
    {
        $field = $configuration['field'];
        $column = $configuration['column'];
        $entity = $configuration['entity'];

        $foreignEntity = $this->getForeignEntityName($entity, $field);

        $sortBy = $this->convertor->getMetadata()->get(['clientDefs', $entity, 'relationshipPanels', $field, 'sortBy']);

        $params = [];
        if (!empty($sortBy)) {
            $asc = $this->convertor->getMetadata()->get(['clientDefs', $entity, 'relationshipPanels', $field, 'asc'], true);
            $params['sortBy'] = $sortBy;
            $params['asc'] = !empty($asc);
        }

        if (!empty($configuration['sortFieldRelation'])) {
            $params['sortBy'] = $configuration['sortFieldRelation'];
            $params['asc'] = $configuration['sortOrderRelation'] !== 'DESC';
        }

        $params['offset'] = empty($configuration['offsetRelation']) ? 0 : (int)$configuration['offsetRelation'];
        $params['maxSize'] = empty($configuration['limitRelation']) ? 20 : (int)$configuration['limitRelation'];

        if (!empty($configuration['channelId'])) {
            $params['exportByChannelId'] = $configuration['channelId'];
        }

        if (!empty($configuration['filterField']) && !empty($configuration['filterFieldValue'])) {
            switch ($this->convertor->getMetadata()->get(['entityDefs', $foreignEntity, 'fields', $configuration['filterField'], 'type'])) {
                case 'bool':
                    switch ($configuration['filterFieldValue']) {
                        case ['+']:
                            $params['where'] = [['type' => 'isTrue', 'attribute' => $configuration['filterField']]];
                            break;
                        case ['-']:
                            $params['where'] = [['type' => 'isFalse', 'attribute' => $configuration['filterField']]];
                            break;
                    }
                    break;
                case 'enum':
                    $params['where'] = [
                        [
                            'type'      => 'in',
                            'attribute' => $configuration['filterField'],
                            'value'     => $configuration['filterFieldValue'],
                        ]
                    ];
                    break;
                case 'multiEnum':
                    $params['where'] = [
                        [
                            'type'      => 'arrayAnyOf',
                            'attribute' => $configuration['filterField'],
                            'value'     => $configuration['filterFieldValue'],
                        ]
                    ];
                    break;
            }
        }

        if (!empty($configuration['searchFilter'])) {
            $params['where'] = !empty($configuration['searchFilter']['where']) ? $configuration['searchFilter']['where'] : [];
        }

        $params['disableCount'] = true;

        try {
            $foreignResult = $this->findLinkedEntities($entity, $record, $field, $params);
        } catch (\Throwable $e) {
            $GLOBALS['log']->error('Export. Can not get foreign entities: ' . $e->getMessage());
        }

        if (empty($configuration['exportIntoSeparateColumns'])) {
            $result[$column] = $configuration['markForNoRelation'];
        }

        $foreignList = [];
        if (isset($foreignResult['collection'])) {
            foreach ($foreignResult['collection'] as $v) {
                $foreignList[] = array_merge($v->toArray(), ['_entity' => $v]);
            }
        } elseif (isset($foreignResult['list'])) {
            $foreignList = $foreignResult['list'];
        }

        $links = [];
        $type = $record['attributeType'] ?? $this->getMetadata()->get(['entityDefs', $entity, 'fields', $field, 'type']);

        if (empty($foreignList)) {
            $links[] = $configuration[$type == 'extensibleMultiEnum' ? 'nullValue' : 'markForNoRelation'];
        }

        $foreignList = array_slice($foreignList, 0, $params['maxSize']);

        $exportBy = isset($configuration['exportBy']) ? $configuration['exportBy'] : ['id'];

        foreach ($foreignList as $foreignData) {
            $fieldResult = [];
            foreach ($exportBy as $v) {
                if ($configuration['zip']) {
                    $foreign = $foreignData['_entity'] ?? $this->convertor->getEntity($foreignEntity, $foreignData['id']);
                    $result['__fileEntities'][] = $foreign;
                }

                $foreignType = $this->convertor->getTypeForField($foreignEntity, $v);

                $this->prepareExportByField($foreignEntity, $v, $foreignType, $foreignData);

                // prepare type for product attribute value
                if ($entity === 'Product' && $field === 'productAttributeValues' && $v === 'value') {
                    $foreignType = $foreignData['attributeType'] === 'file' ? 'varchar' : $foreignData['attributeType'];
                }

                $foreignConfiguration = array_merge($configuration, ['entity'=>  $foreignEntity,'field' => $v]);
                $this->convertForeignType($fieldResult, $foreignType, $foreignConfiguration, $foreignData, $v, $record);
            }

            $links[] = implode($configuration['fieldDelimiterForRelation'], $fieldResult);
        }

        if (!empty($configuration['exportIntoSeparateColumns'])) {
            $k = 0;
            foreach ($links as $k => $link) {
                $columnName = $column;
                if (isset($foreignList[$k])) {
                    foreach ($foreignList[$k] as $relField => $relVal) {
                        if (is_array($relVal) || is_object($relVal)) {
                            continue 1;
                        }
                        $columnName = str_replace('{{' . $relField . '}}', (string)$relVal, $columnName);
                    }
                }

                if ($columnName === $column) {
                    $columnName = $column . '_' . ($k + 1);
                }

                $result[$columnName] = $link;
            }
            if (!empty($configuration['limitRelation']) && is_int($configuration['limitRelation'])) {
                while ($k < ($configuration['limitRelation'] - 1)) {
                    $k++;
                    $columnName = $column . '_' . ($k + 1);
                    $result[$columnName] = $configuration['markForNoRelation'];
                }
            }
        } else {
            $preparedLinks = [];
            foreach ($links as $link) {
                $preparedLinks[] = is_array($link) ? json_encode($link) : (string)$link;
            }
            $result[$column] = implode($configuration['delimiter'], $preparedLinks);
        }
    }

    protected function getLinkedEntitiesKeyForConfiguration(array $configuration): array
    {
        $linkedEntitiesKeys = $this->convertor->getMemoryStorage()->get("{$configuration['id']}_ids") ?? [];

        return $linkedEntitiesKeys[$configuration['id']] ?? [];
    }

    public function queryCallbackForAttribute(Container $container, QueryBuilder $qb, Mapper $mapper, array $conf): void
    {
        if (!class_exists(MigrationHelper::class)) {
            return;
        }

        $attribute = $this->convertor->getAttributeById($conf['attributeId']);
        if (empty($attribute)) {
            return;
        }

        $relColumnName = $mapper->toDb(lcfirst($attribute->getVirtualField('entityType')) . 'Id');
        $relTableName = $mapper->toDb(MigrationHelper::buildRelationName(
            'ProductAttributeValue',
            $conf['attributeId'],
            $attribute->getVirtualField('entityType')
        ));

        $mtAlias = $mapper->getQueryConverter()->getMainTableAlias();

        /** @var \Doctrine\DBAL\Connection $connection */
        $connection = $container->get('connection');

        $channelsIds = [''];
        if (!empty($conf['replaceAttributeValues']) && !empty($conf['channelId'])) {
            $channelsIds[] = $conf['channelId'];
        }

        $language = 'main';

        foreach ($channelsIds as $channelId) {
            $uniqueHash = Util::generateUniqueHash();
            $qb1 = $connection->createQueryBuilder()
                ->select("{$uniqueHash}_rel.$relColumnName")
                ->from($relTableName, "{$uniqueHash}_rel")
                ->innerJoin("{$uniqueHash}_rel", 'product_attribute_value', "{$uniqueHash}_pav",
                    "{$uniqueHash}_pav.id={$uniqueHash}_rel.product_attribute_value_id")
                ->where("{$uniqueHash}_pav.attribute_id = :{$uniqueHash}_attributeId")
                ->andWhere("{$uniqueHash}_pav.deleted = :false")
                ->andWhere("{$uniqueHash}_rel.deleted = :false")
                ->andWhere("{$uniqueHash}_pav.channel_id = :{$uniqueHash}_channelId")
                ->andWhere("{$uniqueHash}_pav.language = :{$uniqueHash}_language")
                ->andWhere("{$uniqueHash}_pav.product_id = mt_alias.id")
                ->setParameter("{$uniqueHash}_attributeId", $conf['attributeId'])
                ->setParameter("{$uniqueHash}_channelId", $channelId)
                ->setParameter("{$uniqueHash}_language", $language)
                ->setParameter("false", false, ParameterType::BOOLEAN);

            $innerSql = str_replace('mt_alias', $mtAlias, $qb1->getSQL());

            if (Converter::isPgSQL($container->get('connection'))) {
                $qb->addSelect("(SELECT string_agg({$uniqueHash}_c.$relColumnName::text, ',') FROM ($innerSql) AS {$uniqueHash}_c) AS {$conf['id']}_{$channelId}_{$language}");
            } else {
                $qb->addSelect("(SELECT GROUP_CONCAT({$uniqueHash}_c.$relColumnName SEPARATOR ',') FROM ($innerSql) AS {$uniqueHash}_c) AS {$conf['id']}_{$channelId}_{$language}");
            }

            foreach ($qb1->getParameters() as $pName => $pValue) {
                $qb->setParameter($pName, $pValue, $mapper::getParameterType($pValue));
            }
        }
    }

    public function queryCallback(Container $container, QueryBuilder $qb, Mapper $mapper, array $configuration): void
    {
        if (!empty($configuration['attributeId'])) {
            $this->queryCallbackForAttribute($container, $qb, $mapper, $configuration);
            return;
        }

        $linkDefs = $this
            ->getMetadata()
            ->get(['entityDefs', $configuration['entity'], 'links', $configuration['field']]);

        if (empty($linkDefs['entity'])) {
            throw new Error("Invalid relation metadata");
        }

        $uniqueHash = Util::generateUniqueHash();

        $mtAlias = $mapper->getQueryConverter()->getMainTableAlias();

        $entity = $this->convertor->getEntityManager()->getEntity($configuration['entity']);
        $keySet = $mapper->getKeys($entity, $configuration['field']);

        /** @var \Espo\Core\SelectManagers\Base $selectManager */
        $selectManager = $container->get('selectManagerFactory')->create($linkDefs['entity']);

        $sp = $selectManager->getSelectParams([
            'where'   => $configuration['searchFilter']['where'] ?? null,
            'sortBy'  => $configuration['sortFieldRelation'] ?? 'id',
            'asc'     => $configuration['sortOrderRelation'] === 'ASC',
            'offset'  => $configuration['offsetRelation'] ?? 0,
            'maxSize' => $configuration['limitRelation'] ?? 5
        ], true, true);

        $sp['select'] = ['id'];

        $entity = $this->convertor->getEntityManager()->getEntity($linkDefs['entity']);
        $qb1 = $mapper->createSelectQueryBuilder($entity, $sp, true);
        $qb1->select("$mtAlias.id AS {$configuration['id']}_col");

        if (empty($linkDefs['relationName'])) {
            $foreignKey = $mapper->getQueryConverter()->toDb($keySet['foreignKey']);
            $qb1->andWhere("$mtAlias.$foreignKey=mt_alias.id");
        } else {
            $nearColumn = $mapper->getQueryConverter()->toDb($keySet['nearKey']);
            $distantColumn = $mapper->getQueryConverter()->toDb($keySet['distantKey']);

            $relTable = $mapper->getQueryConverter()->toDb($linkDefs['relationName']);
            $relTableAlias = $uniqueHash . '_r';

            $qb1->join($mtAlias, $relTable, $relTableAlias, "$mtAlias.id=$relTableAlias.$distantColumn AND $relTableAlias.deleted=:false");
            $qb1->setParameter('false', false, ParameterType::BOOLEAN);
            $qb1->andWhere("$relTableAlias.$nearColumn=mt_alias.id");
        }

        $innerSql = str_replace([$mtAlias, 'mt_alias'], ['a_' . $uniqueHash, $mtAlias], $qb1->getSQL());

        if (Converter::isPgSQL($container->get('connection'))) {
            $qb->addSelect("(SELECT string_agg({$uniqueHash}_c.{$configuration['id']}_col::text, ',') FROM ($innerSql) AS {$uniqueHash}_c) AS {$configuration['id']}");
        } else {
            $qb->addSelect("(SELECT GROUP_CONCAT({$uniqueHash}_c.{$configuration['id']}_col SEPARATOR ',') FROM ($innerSql) AS {$uniqueHash}_c) AS {$configuration['id']}");
        }

        foreach ($qb1->getParameters() as $pName => $pValue) {
            $qb->setParameter($pName, $pValue, $mapper::getParameterType($pValue));
        }
    }

    protected function findLinkedEntities(string $entity, array $record, string $field, array $params): array
    {
        $configuration = $this->getMemoryStorage()->get('configurationItemData');
        if (empty($configuration['id'])) {
            throw new \Error('No configuration id found.');
        }

        $relEntityType = $this->getForeignEntityName($entity, $field);

        // load to memory
        $this->loadToMemory($relEntityType, $configuration);

        $collection = new EntityCollection([], $relEntityType);
        if (!empty($configuration['attributeId'])) {
            if (!empty($record[$configuration['field']])) {
                foreach (explode(',', $record[$configuration['field']]) as $id) {
                    if ($id && trim($id) !== '') {
                        $collection->append($this->getMemoryStorage()->get($this->createKey($configuration['id'], $id)));
                    }
                }
            }
        } else {
            if (!empty($record['_entity']->rowData[$configuration['id']])) {
                $ids = explode(',', $record['_entity']->rowData[$configuration['id']]);
                foreach ($ids as $id) {
                    if ($id && trim($id) !== '') {
                        $collection->append($this->getMemoryStorage()->get($this->createKey($configuration['id'], $id)));
                    }
                }
            }
        }

        return ['collection' => $collection];
    }

    protected function loadToMemory(string $relEntityType, array $configuration): void
    {
        $checkNumber = $this->getMemoryStorage()->get('linkMultipleTypeNumber');
        $offset = $this->getMemoryStorage()->get('exportRecordsPartOffset');
        if (!empty($this->getMemoryStorage()->get("{$configuration['id']}_ids")) && $checkNumber === $offset) {
            return;
        }

        $this->getMemoryStorage()->set('linkMultipleTypeNumber', $offset);

        $ids = [];
        foreach ($this->getMemoryStorage()->get('exportRecordsPart') ?? [] as $record) {
            if (!empty($configuration['attributeId'])) {
                if (!empty($record[$configuration['field']])) {
                    foreach (explode(',', $record[$configuration['field']]) as $id) {
                        if ($id && trim($id) !== '' && !in_array($id, $ids)) {
                            $ids[] = $id;
                        }
                    }
                }
            } else {
                if (!empty($record['_entity']->rowData[$configuration['id']])) {
                    foreach (explode(',', $record['_entity']->rowData[$configuration['id']]) as $id) {
                        if ($id && trim($id) !== '' && !in_array($id, $ids)) {
                            $ids[] = $id;
                        }
                    }
                }
            }
        }

        $res = $this->convertor->getService($relEntityType)
            ->findEntities([
                'where'        => [
                    [
                        'type'      => 'in',
                        'attribute' => 'id',
                        'value'     => $ids
                    ]
                ],
                'disableCount' => true,
                'maxSize'      => count($ids)
            ]);

        $linkedEntitiesKeys = [];
        foreach ($res['collection'] as $re) {
            $key = $this->createKey($configuration['id'], $re->get('id'));
            $this->getMemoryStorage()->set($key, $re);
            $linkedEntitiesKeys[$configuration['id']][] = $key;
        }

        $this->getMemoryStorage()->set("{$configuration['id']}_ids", $linkedEntitiesKeys);
    }

    protected function createKey(string $configurationId, string $id): string
    {
        return "export_{$configurationId}_id_{$id}";
    }
}
