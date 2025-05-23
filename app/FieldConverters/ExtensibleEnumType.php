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

use Espo\Entities\ExtensibleEnumOption;
use Espo\ORM\Entity;

class ExtensibleEnumType extends LinkType
{
    protected function getFieldName(string $field): string
    {
        return $field;
    }

    protected function getForeignEntityName(string $entity, string $field): string
    {
        return 'ExtensibleEnumOption';
    }

    protected function needToCallForeignEntity(array $exportBy): bool
    {
        return true;
    }

    public function getEntity(string $scope, string $id): ?Entity
    {
        $itemKey = $this->convertor->getEntityManager()->getRepository('ExtensibleEnumOption')->getCacheKey($id);

        return $this->getMemoryStorage()->get($itemKey);
    }

    protected function prepareEntity(Entity $entity, array $config): void
    {
        if (!$entity instanceof ExtensibleEnumOption) {
            return;
        }

        $extensibleEnumId = $this->getMetadata()->get(['entityDefs', $config['entity'], 'fields', $config['field'], 'extensibleEnumId']);

        if (empty($extensibleEnumId)) {
            return;
        }

        $data = $this->convertor
            ->getEntityManager()
            ->getRepository('ExtensibleEnumOption')
            ->getPreparedOption($extensibleEnumId, $entity->get('id'));

        if (!empty($data) && is_array($data) && array_key_exists('preparedName', $data)) {
            $entity->set('name', $data['preparedName']);
        }
    }
}
