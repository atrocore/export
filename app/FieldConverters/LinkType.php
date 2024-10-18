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

namespace Export\FieldConverters;

use Doctrine\DBAL\Query\QueryBuilder;
use Espo\ORM\Entity;
use Espo\ORM\EntityCollection;

class LinkType extends AbstractType
{
    public const MEMORY_KEY = 'linked_entities_keys';
    public const MEMORY_EXPORT_BY_KEY = 'export_by';

    public function convertToString(array &$result, array $record, array $configuration): void
    {
        $field = $configuration['field'];
        $column = $configuration['column'];
        $entity = $configuration['entity'];

        $result[$column] = $configuration['markForNoRelation'];

        $linkId = $record[$this->getFieldName($field)];
        $metadata = $this->getMetadata()->get(['entityDefs', $entity, 'fields', $field]);
        $type = $record['attributeType'] ?? $metadata['type'] ?? null;
        $isUnit = ($configuration['attributeValue'] ?? null) == 'valueUnit' || ($type == 'link' && !empty($metadata['unitIdField']));

        if ($type == 'extensibleEnum' || $isUnit) {
            $result[$column] = $configuration['nullValue'];
        }

        if (!empty($linkId)) {
            $result[$column] = $configuration['nullValue'];
            $exportBy = !empty($configuration['exportBy']) ? $configuration['exportBy'] : ['name'];

            if ($this->needToCallForeignEntity($exportBy) || $configuration['zip']) {
                $foreignEntity = $this->getForeignEntityName($entity, $field);
                if (!empty($foreignEntity)) {
                    try {
                        $this->loadLinkDataToMemory($record, $entity, $field);
                        $foreign = $this->getEntity($foreignEntity, $linkId);
                    } catch (\Throwable $e) {
                        $GLOBALS['log']->error('Export. Can not get foreign entity: ' . $e->getMessage());
                    }
                }

                if (!empty($foreign)) {
                    if ($configuration['zip']) {
                        $result['__fileEntities'] = [];
                    }
                    /**
                     * For main image
                     */
                    if ($field === 'mainImage' && in_array($entity, ['Category', 'Product'])) {
                        if ($configuration['zip']) {
                            $result['__fileEntities'][] = $foreign;
                        }
                        $this->convertor->getService('File')->prepareEntityForOutput($foreign);
                    } else {
                        if ($foreignEntity === 'File') {
                            if ($configuration['zip']) {
                                $result['__fileEntities'][] = $foreign;
                            }
                        }
                    }

                    $foreignData = $foreign->toArray();
                    $fieldResult = [];
                    foreach ($exportBy as $v) {
                        $foreignType = $this->convertor->getTypeForField($foreignEntity, $v);

                        $this->prepareExportByField($foreignEntity, $v, $foreignType, $foreignData);

                        $foreignConfiguration = array_merge($configuration, ['field' => $v, 'attributeId' => null]);
                        $this->convertForeignType($fieldResult, $foreignType, $foreignConfiguration, $foreignData, $v, $record);

                        if ($configuration['zip']) {
                            $result['__fileEntities'][] = $foreign;
                        }
                    }

                    if (!empty($fieldResult)) {
                        $result[$column] = implode($configuration['fieldDelimiterForRelation'], $fieldResult);
                    }
                } else {
                    $result[$column] = $configuration['emptyValue'];
                }
            } else {
                $fieldResult = [];
                foreach ($exportBy as $v) {
                    $key = $field . ucfirst($v);
                    if (isset($record[$key])) {
                        $fieldResult[] = $record[$key];
                    }
                }

                if (!empty($fieldResult)) {
                    $result[$column] = implode($configuration['fieldDelimiterForRelation'], $fieldResult);
                }
            }
        }
    }

    protected function getLinkedEntitiesKeyForConfiguration(array $configuration): array
    {
        $linkedEntitiesKeys = $this->convertor->getMemoryStorage()->get(self::MEMORY_KEY) ?? [];

        return $linkedEntitiesKeys[$configuration['id']] ?? [];
    }

    protected function prepareExportByField(string $foreignEntity, string $configuratorField, string &$foreignType, array &$foreignData): void
    {
        if ($configuratorField === 'sharedDownloadUrl') {
            $foreignData['sharedDownloadUrl'] = $this->getSharedDownloadUrl($this->getMemoryStorage()->get('exportJobId'), $foreignData['id']);
            return;
        }

        $exportByFieldParts = explode(".", $configuratorField);
        $parts = count($exportByFieldParts);
        if ($parts !== 2 && $parts !== 3) {
            return;
        }

        if (empty($exportByFieldParts[$parts - 1])) {
            return;
        }

        $configuration = $this->getMemoryStorage()->get('configurationItemData');

        $relEntityType = $this->getMetadata()->get(['entityDefs', $foreignEntity, 'links', $exportByFieldParts[0], 'entity']);

        $exportByKeys = $this->convertor->getMemoryStorage()->get(self::MEMORY_EXPORT_BY_KEY) ?? [];

        // load to memory if it needs
        if (!isset($exportByKeys[$configuration['id']])) {
            $keys = $this->getLinkedEntitiesKeyForConfiguration($configuration);

            $ids = [];
            foreach ($keys as $v) {
                $ids[] = $this->convertor->getMemoryStorage()->get($v)->get($exportByFieldParts[0] . 'Id');
            }

            $res = $this->convertor->getService($relEntityType)->findEntities([
                'where'        => [['type' => 'in', 'attribute' => 'id', 'value' => $ids]],
                'disableCount' => true
            ]);

            foreach ($res['collection'] as $entity) {
                $itemKey = $this->convertor->getEntityManager()->getRepository($entity->getEntityType())->getCacheKey($entity->get('id'));
                $this->getMemoryStorage()->set($itemKey, $entity);
                $exportByKeys[$configuration['id']][] = $itemKey;
            }
            $this->getMemoryStorage()->set(self::MEMORY_EXPORT_BY_KEY, $exportByKeys);
        }

        $foreignLinkData = ['collection' => new EntityCollection([], $relEntityType)];

        foreach ($exportByKeys[$configuration['id']] as $exportByKey) {
            $relEntity = $this->getMemoryStorage()->get($exportByKey);
            if ($foreignData[$exportByFieldParts[0] . 'Id'] === $relEntity->get('id')) {
                $foreignLinkData['collection']->append($relEntity);
            }
        }

        if (empty($foreignLinkData['collection'][0])) {
            $foreignData[$configuratorField] = null;
            return;
        }

        if ($parts === 3) {
            $foreignLinkData = $this->convertor->getService($foreignLinkData['collection'][0]->getEntityType())->findLinkedEntities(
                $foreignLinkData['collection'][0]->get('id'), $exportByFieldParts[1], ['disableCount' => true]
            );
            if (empty($foreignLinkData['total'])) {
                $foreignData[$configuratorField] = null;
                return;
            }
        }

        if ($exportByFieldParts[$parts - 1] === 'sharedDownloadUrl') {
            $foreignData[$configuratorField] = $this->getSharedDownloadUrl($this->getMemoryStorage()->get('exportJobId'), $foreignLinkData['collection'][0]->get('id'));
            return;
        }

        $foreignData[$configuratorField] = $foreignLinkData['collection'][0]->get($exportByFieldParts[$parts - 1]);


        $foreignType = $this->convertor->getTypeForField($foreignLinkData['collection'][0]->getEntityType(), $exportByFieldParts[$parts - 1]);
    }

    protected function convertForeignType(array &$fieldResult, string $foreignType, array $foreignConfiguration, array $foreignData, string $field, array $record)
    {
        $column = $foreignConfiguration['column'];

        if ($foreignType === 'link') {
            $fieldResult[$field] = $foreignConfiguration['nullValue'];
            $fieldId = $field . 'Id';
            if (isset($record[$fieldId])) {
                if (empty($record[$fieldId])) {
                    $fieldResult[$field] = $record[$fieldId] === null ? $foreignConfiguration['nullValue'] : $foreignConfiguration['emptyValue'];
                } else {
                    $fieldResult[$field] = $record[$fieldId];
                }
            }
        } elseif ($foreignType === 'linkMultiple') {
            $fieldResult[$field] = $foreignConfiguration['nullValue'];
        } elseif ($foreignType === 'image' || $foreignType === 'asset') {
            $fieldResult[$field] = $foreignConfiguration['nullValue'];
            $fieldId = $field . 'Id';
            if (isset($record[$fieldId])) {
                if (empty($record[$fieldId])) {
                    $fieldResult[$field] = $record[$fieldId] === null ? $foreignConfiguration['nullValue'] : $foreignConfiguration['emptyValue'];
                } else {
                    if (!empty($attachment = $this->convertor->getEntity('Attachment', $record[$fieldId]))) {
                        $fieldResult[$field] = $attachment->get('url');
                    } else {
                        $fieldResult[$field] = $foreignConfiguration['emptyValue'];
                    }
                }
            }
        } else {
            $fieldResult[$field] = $this->convertor->convertType($foreignType, $foreignData, $foreignConfiguration)[$column];
        }
    }

    protected function getFieldName(string $field): string
    {
        return $field . 'Id';
    }

    protected function getForeignEntityName(string $entity, string $field): string
    {
        $configuration = $this->getMemoryStorage()->get('configurationItemData');
        if (!empty($configuration['attributeId'])) {
            $attribute = $this->convertor->getAttributeById($configuration['attributeId']);
            if (in_array($attribute->get('type'), ['link', 'linkMultiple'])) {
                return $attribute->getVirtualField('entityType');
            }
        }

        return $this->convertor->getMetadata()->get(['entityDefs', $entity, 'links', $field, 'entity']);
    }

    protected function needToCallForeignEntity(array $exportBy): bool
    {
        $configuration = $this->getMemoryStorage()->get('configurationItemData');
        if (!empty($configuration['attributeId'])) {
            return true;
        }

        foreach ($exportBy as $v) {
            if (!in_array($v, ['id', 'name'])) {
                return true;
            }
        }

        return false;
    }

    protected function loadLinkDataToMemory(array $record, string $entity, string $field): void
    {
        $configuration = $this->getMemoryStorage()->get('configurationItemData');
        if (empty($configuration['id'])) {
            throw new \Error('No configuration id found.');
        }

        $records = $this->getMemoryStorage()->get('exportRecordsPart') ?? [];

        $fieldName = $this->getFieldName($field);

        $linkedEntitiesKeys = $this->getMemoryStorage()->get(self::MEMORY_KEY) ?? [];
        if (isset($linkedEntitiesKeys[$configuration['id']])) {
            return;
        }

        $foreignEntity = $this->getForeignEntityName($entity, $field);

        $ids = [];
        foreach ($records as $v) {
            if (array_key_exists('prepareUnitValueFnc', $configuration)) {
                list($valueFrom, $valueTo, $val) = $configuration['prepareUnitValueFnc']((string)$v[$field]);
            } else {
                $val = $v[$fieldName];
            }
            if (!empty($val)) {
                if (is_array($val)) {
                    $ids = array_merge($ids, $val);
                } else {
                    $ids[] = $val;
                }
            }
        }

        $params['offset'] = 0;
        $params['maxSize'] = $this->convertor->getConfig()->get('exportMemoryItemsCount', 10000);
        $params['disableCount'] = true;
        $params['where'] = [['type' => 'in', 'attribute' => 'id', 'value' => $ids]];

        $res = $this->findEntities($foreignEntity, $params);

        foreach ($res['collection'] as $re) {
            $this->prepareEntity($re, $configuration);
            $itemKey = $this->convertor->getEntityManager()->getRepository($re->getEntityType())->getCacheKey($re->get('id'));
            $this->getMemoryStorage()->set($itemKey, $re);
            $linkedEntitiesKeys[$configuration['id']][] = $itemKey;
        }
        $this->getMemoryStorage()->set(self::MEMORY_KEY, $linkedEntitiesKeys);
    }

    protected function findEntities(string $foreignEntity, array $params): array
    {
        return $this->convertor->getService($foreignEntity)->findEntities($params);
    }

    protected function getEntity(string $scope, string $id): ?Entity
    {
        $itemKey = $this->convertor->getEntityManager()->getRepository($scope)->getCacheKey($id);

        return $this->getMemoryStorage()->get($itemKey);
    }

    protected function prepareEntity(Entity $entity, array $config): void
    {
    }

    protected function prepareQueryCallbackForAttribute(QueryBuilder $qb, array $conf, string $alias): void
    {
        $qb->select("$alias.reference_value");
    }
}
