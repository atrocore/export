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

use Atro\ORM\DB\RDB\Mapper;
use Doctrine\DBAL\Query\QueryBuilder;
use Espo\ORM\EntityCollection;
use Espo\ORM\IEntity;

class LinkMultipleType extends LinkType
{
    public const MEMORY_RELATION_KEY = 'relRecords';

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

    public function queryCallback(QueryBuilder $qb, IEntity $relEntity, array $params, Mapper $mapper): void
    {
        echo '<pre>';
        print_r('333');
        die();



//        $textFilterParams = [];
//        foreach ($this->textFilterParams as $row) {
//            if (isset($row['value']) || $row['value'] !== '') {
//                $this->textFilter($row['value'], $textFilterParams);
//            }
//        }
//
//        $textFilterQuery = $mapper->createSelectQueryBuilder($relEntity, $textFilterParams);
//
//        if (empty($this->textFilter)) {
//            return;
//        }
//
//        $textFilter = $this->textFilter;
//        if (mb_strpos($textFilter, 'ft:') === 0) {
//            $textFilter = mb_substr($textFilter, 3);
//        }
//
//        $tableAlias = $mapper->getQueryConverter()->getMainTableAlias();
//
//        /** @var \Pim\Repositories\ProductAttributeValue $pavRepo */
//        $pavRepo = $this->getEntityManager()->getRepository('ProductAttributeValue');
//
//        $where = [
//            'type'  => 'and',
//            'value' => [
//                [
//                    'type'      => 'in',
//                    'attribute' => 'attributeType',
//                    'value'     => ['varchar', 'text', 'wysiwyg', 'extensibleEnum']
//                ],
//                [
//                    'type'  => 'or',
//                    'value' => [
//                        [
//                            'type'      => 'like',
//                            'attribute' => 'textValue',
//                            'value'     => "$textFilter%"
//                        ],
//                        [
//                            'type'      => 'like',
//                            'attribute' => 'varcharValue',
//                            'value'     => "$textFilter%"
//                        ]
//                    ]
//                ]
//            ]
//        ];
//
//        $sp = $this->createSelectManager('ProductAttributeValue')->getSelectParams(['where' => [$where]], true, true);
//        $sp['select'] = ['productId'];
//
//        $qb1 = $pavRepo->getMapper()->createSelectQueryBuilder($pavRepo->get(), $sp);
//        $qb->andWhere(
//            $qb->expr()->or(
//                "{$tableAlias}.id IN ({$qb1->getSql()})",
//                $textFilterQuery->getQueryPart('where')
//            )
//        );
//
//        foreach (array_merge($qb1->getParameters(), $textFilterQuery->getParameters()) as $param => $val) {
//            $qb->setParameter($param, $val, Mapper::getParameterType($val));
//        }
    }

    protected function findLinkedEntities(string $entity, array $record, string $field, array $params): array
    {
        $configuration = $this->getMemoryStorage()->get('configurationItemData');
        if (empty($configuration['id'])){
            throw new \Error('No configuration id found.');
        }

        $records = $this->getMemoryStorage()->get('exportRecordsPart') ?? [];

        // load to memory
        $this->loadToMemory($records, $entity, $field, $params, $configuration);

        $relEntityType = $this->getMetadata()->get(['entityDefs', $entity, 'links', $field, 'entity']);

        $collection = new EntityCollection([], $relEntityType);

        $linkedEntitiesKeys = $this->getMemoryStorage()->get(self::MEMORY_KEY) ?? [];
        if (!isset($linkedEntitiesKeys[$configuration['id']])) {
            return ['collection' => $collection];
        }

        $keySet = $this->getKeySet($entity, $field);

        $nearKey = $keySet['nearKey'] ?? $keySet['foreignKey'];

        $number = 0;

        $relRecords = $this->getMemoryStorage()->get(self::MEMORY_RELATION_KEY) ?? [];
        foreach ($linkedEntitiesKeys[$configuration['id']] as $key) {
            $relEntity = $this->getMemoryStorage()->get($key);
            $relIds = $relRecords[$configuration['id']][$relEntity->get('id')] ?? null;
            if ($relIds !== null) {
                if (!in_array($record[$keySet['key']], $relIds)) {
                    continue;
                }
            } else {
                if ($relEntity->get($nearKey) !== $record[$keySet['key']]) {
                    continue;
                }
            }

            if (isset($params['offset']) && $number < $params['offset']) {
                $number++;
                continue;
            }

            if (isset($params['maxSize']) && $collection->count() >= $params['maxSize']) {
                break;
            }

            $collection->append($relEntity);
        }

        return ['collection' => $collection];
    }

    protected function loadToMemory(array $records, string $entityType, string $relationName, array $params, array $configuration): void
    {
        $linkedEntitiesKeys = $this->getMemoryStorage()->get(self::MEMORY_KEY) ?? [];
        if (isset($linkedEntitiesKeys[$configuration['id']])) {
            return;
        }

        $params['offset'] = 0;
        $params['maxSize'] = $this->convertor->getConfig()->get('exportMemoryItemsCount', 10000);

        $linkDefs = $this->getMetadata()->get(['entityDefs', $entityType, 'links', $relationName]);

        if (!isset($linkDefs['entity'])) {
            throw new \Error("Metadata error. No 'entity' parameter for '$relationName' relation.");
        }

        if ($linkDefs['type'] === 'belongsTo') {
            $params['where'][] = [
                'type'      => 'in',
                'attribute' => 'id',
                'value'     => array_column($records, lcfirst($linkDefs['entity']) . 'Id')
            ];
        } else {
            if (empty($linkDefs['foreign'])) {
                throw new \Error("Metadata error. No 'foreign' parameter for '$relationName' relation.");
            }
            $params['where'][] = [
                'type'      => 'linkedWith',
                'attribute' => $linkDefs['foreign'],
                'value'     => array_column($records, 'id')
            ];
        }

        $res = $this->convertor->getService($linkDefs['entity'])->findEntities($params);

        // load relation ids
        if (!empty($res['collection'][0]) && $linkDefs['type'] === 'hasMany' && !empty($linkDefs['relationName'])) {
            $keySet = $this->getKeySet($entityType, $relationName);
            $relationCollection = $this->convertor->getEntityManager()->getRepository(ucfirst($linkDefs['relationName']))
                ->select(['id', $keySet['nearKey'], $keySet['distantKey']])
                ->where([
                    $keySet['nearKey']    => array_column($records, 'id'),
                    $keySet['distantKey'] => array_column($res['collection']->toArray(), 'id'),
                ])
                ->find();
            $relRecords = $this->getMemoryStorage()->get(self::MEMORY_RELATION_KEY) ?? [];
            foreach ($relationCollection as $relEntity) {
                $relRecords[$configuration['id']][$relEntity->get($keySet['distantKey'])][] = $relEntity->get($keySet['nearKey']);
            }
            $this->getMemoryStorage()->set(self::MEMORY_RELATION_KEY, $relRecords);
        }

        foreach ($res['collection'] as $re) {
            $itemKey = $this->convertor->getEntityManager()->getRepository($re->getEntityType())->getCacheKey($re->get('id'));
            $this->getMemoryStorage()->set($itemKey, $re);
            $linkedEntitiesKeys[$configuration['id']][] = $itemKey;
        }
        $this->getMemoryStorage()->set(self::MEMORY_KEY, $linkedEntitiesKeys);
    }

    public function getKeySet(string $entityType, string $link): array
    {
        $entityRepository = $this->convertor->getEntityManager()->getRepository($entityType);
        return $entityRepository->getMapper()->getKeys($entityRepository->get(), $link);
    }
}
