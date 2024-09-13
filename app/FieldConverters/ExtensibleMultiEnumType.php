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

    protected function findLinkedEntities(string $entity, array $record, string $field, array $params): array
    {
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

    public function queryCallbackForAttribute(Container $container, QueryBuilder $qb, Mapper $mapper, array $conf): void
    {
        AbstractType::queryCallbackForAttribute($container, $qb, $mapper, $conf);
    }

    protected function prepareQueryCallbackForAttribute(QueryBuilder $qb, array $conf, string $alias): void
    {
        $qb->select("$alias.text_value");
    }

    protected function findEntities(string $foreignEntity, array $params): array
    {
        $configuration = $this->getMemoryStorage()->get('configurationItemData');
        if (empty($configuration['id'])) {
            throw new \Error('No configuration id found.');
        }

        if (!empty($configuration['attributeId'])) {
            $key = $this->convertor->getEntityManager()->getRepository('Attribute')->getCacheKey($configuration['attributeId']);

            if (!$this->getMemoryStorage()->has($key)) {
                $attribute = $this->convertor->getEntity('Attribute', $configuration['attributeId']);
                $this->getMemoryStorage()->set($key, $attribute);
            } else {
                $attribute = $this->getMemoryStorage()->get($key);
            }

            $params['sortBy'] = 'extensible_enum_extensible_enum_option_mm.sorting';
            $params['asc'] = true;

            return $this->convertor->getService('ExtensibleEnum')->findLinkedEntities($attribute->get('extensibleEnumId'), 'extensibleEnumOptions', $params);
        }

        return parent::findEntities($foreignEntity, $params);
    }
}
