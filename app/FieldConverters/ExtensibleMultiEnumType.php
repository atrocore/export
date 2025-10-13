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

use Atro\Core\Container;
use Atro\ORM\DB\RDB\Mapper;
use Doctrine\DBAL\Query\QueryBuilder;
use Espo\ORM\EntityCollection;

class ExtensibleMultiEnumType extends LinkMultipleType
{
    protected function getFieldName(string $field): string
    {
        return $field;
    }

    protected function getForeignEntityName(string $entity, string $field): string
    {
        return 'ExtensibleEnumOption';
    }

    protected function findLinkedEntities(string $entity, array $record, string $field, array $params, array $configuration): array
    {
        if (!empty($configuration['exportPav'])) {
            $res = $this
                ->findEntities('ExtensibleEnumOption', [
                    'where' => [
                        [
                            'type'      => 'in',
                            'attribute' => 'id',
                            'value'     => $record['value']
                        ]
                    ]
                ]);

            return ['collection' => $res['collection']];
        }

        $collection = new EntityCollection([], 'ExtensibleEnumOption');

        // load to memory
        $this->loadLinkDataToMemory($record, $entity, $field);

        $configuration = $this->getMemoryStorage()->get('configurationItemData');

        $linkedEntitiesKeys = $this->getMemoryStorage()->get(self::MEMORY_KEY) ?? [];

        if (!isset($linkedEntitiesKeys[$configuration['id']])) {
            return ['collection' => $collection];
        }

        if (isset($record[$field]) && is_array($record[$field])) {
            foreach ($linkedEntitiesKeys[$configuration['id']] as $v) {
                $option = $this->getMemoryStorage()->get($v);
                if (in_array($option->get('id'), $record[$field])) {
                    $collection->append($option);
                }
            }
        }

        return ['collection' => $collection];
    }

    public function queryCallback(Container $container, QueryBuilder $qb, Mapper $mapper, array $configuration): void
    {
        $defs = $this
            ->getMetadata()
            ->get(['entityDefs', $configuration['entity'], 'fields', $configuration['field']]);

        if (is_array($defs) && !empty($defs['type']) && $defs['type'] === 'extensibleMultiEnum') {
            return;
        }

        parent::queryCallback($container, $qb, $mapper, $configuration);
    }

    protected function findEntities(string $foreignEntity, array $params, array $data = []): array
    {
        $configuration = $this->getMemoryStorage()->get('configurationItemData');
        if (empty($configuration['id'])) {
            throw new \Error('No configuration id found.');
        }

        if (empty($data['entity']) || empty($data['field'])) {
            return parent::findEntities($foreignEntity, $params, $data);
        }

        $extensibleEnumId = $this->getMetadata()->get(['entityDefs', $data['entity'], 'fields', $data['field'], 'extensibleEnumId']);

        if (empty($extensibleEnumId)) {
            throw new \Error('Extensible enum is not found.');
        }

        $sortBy = $this->getMetadata()->get(['clientDefs', 'ExtensibleEnum', 'relationshipPanels', 'extensibleEnumOptions', 'dragDrop', 'sortField'], 'sortOrder');

        if (!empty($sortBy)) {
            $params['sortBy'] = $sortBy;
        }

        return $this
            ->convertor
            ->getService('ExtensibleEnum')
            ->findLinkedEntities($extensibleEnumId, 'extensibleEnumOptions', $params);
    }
}
