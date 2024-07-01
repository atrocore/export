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
    public const MEMORY_EXTENSIBLE_ENUMS = 'extensibleEnums';

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

    /**
     * @param Entity $entity
     *
     * @return void
     */
    protected function prepareEntity(Entity $entity): void
    {
        if (!$entity instanceof ExtensibleEnumOption) {
            return;
        }

        $extensibleEnumId = null;
        $extensibleEnums = $this->getMemoryStorage()->get(self::MEMORY_EXTENSIBLE_ENUMS) ?? [];

        foreach ($extensibleEnums as $eeId => $eeoIds) {
            if (in_array($entity->get('id'), $eeoIds)) {
                $extensibleEnumId = $eeId;
                break;
            }
        }

        if (empty($extensibleEnumId)) {
            $ee = $entity->get('extensibleEnums');
            if (count($ee) == 0 || empty($extensibleEnumId = $ee[0]->get('id'))) {
                return;
            }

            $extensibleEnums[$extensibleEnumId] = array_column($ee[0]->get('extensibleEnumOptions')->toArray(), 'id');
            $this->getMemoryStorage()->set(self::MEMORY_EXTENSIBLE_ENUMS, $extensibleEnums);
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
