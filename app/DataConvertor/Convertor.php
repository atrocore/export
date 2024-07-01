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

namespace Export\DataConvertor;

use Atro\Core\EventManager\Manager;
use Atro\Core\KeyValueStorages\StorageInterface;
use Espo\Core\Container;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Metadata;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Espo\Services\Record;
use Export\FieldConverters\LinkMultipleType;
use Export\FieldConverters\LinkType;

class Convertor
{
    protected Container $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public function convert(array $record, array $configuration): array
    {
        if ($configuration['type'] == 'Fixed value') {
            if (isset($configuration['fixedValue'])) {
                return [$configuration['column'] => (string)$configuration['fixedValue']];
            }
            return [$configuration['column'] => ""];
        }

        $type = $this->getTypeForField($configuration['entity'], $configuration['field']);

        return $this->convertType($type, $record, $configuration);
    }

    public function convertType(string $type, array $record, array $configuration): array
    {
        $result = [];

        if ($configuration['type'] === 'script') {
            $template = $configuration['script'] ?? '';
            $templateData = [
                'record'        => $record,
                'configuration' => $configuration
            ];
            $result[$configuration['column']] = $this->container->get('twig')->renderTemplate((string)$template, $templateData);
            return $result;
        }

        $fieldConverterClass = '\Export\FieldConverters\\' . ucfirst($type) . 'Type';
        if (!class_exists($fieldConverterClass) || !is_a($fieldConverterClass, \Export\FieldConverters\AbstractType::class, true)) {
            $fieldConverterClass = '\Export\FieldConverters\VarcharType';
        }

        $this->getMemoryStorage()->set('configurationItemData', $configuration);

        $fieldConverter = new $fieldConverterClass($this);
        $fieldConverter->convertToString($result, $record, $configuration);

        return $result;
    }

    public function getEntity(string $scope, string $id)
    {
        return $this->getService($scope)->getEntity($id);
    }

    public function clearMemoryOfLoadedEntities(): void
    {
        foreach ($this->getMemoryStorage()->get(LinkType::MEMORY_KEY) ?? [] as $keys) {
            foreach ($keys as $key) {
                $this->getMemoryStorage()->delete($key);
            }
        }
        $this->getMemoryStorage()->delete(LinkType::MEMORY_KEY);

        foreach ($this->getMemoryStorage()->get(LinkType::MEMORY_EXPORT_BY_KEY) ?? [] as $keys) {
            foreach ($keys as $key) {
                $this->getMemoryStorage()->delete($key);
            }
        }
        $this->getMemoryStorage()->delete(LinkType::MEMORY_EXPORT_BY_KEY);

        $this->getMemoryStorage()->delete(LinkMultipleType::MEMORY_RELATION_KEY);
    }

    public function getMemoryStorage(): StorageInterface
    {
        return $this->container->get('memoryStorage');
    }

    public function getMetadata(): Metadata
    {
        return $this->container->get('metadata');
    }

    public function getConfig(): Config
    {
        return $this->container->get('config');
    }

    public function getService(string $serviceName): Record
    {
        return $this->container->get('serviceFactory')->create($serviceName);
    }

    public function getEntityManager(): EntityManager
    {
        return $this->container->get('entityManager');
    }

    public function getEventManager(): Manager
    {
        return $this->container->get('eventManager');
    }

    public function translate(string $key, string $tab, string $scope): string
    {
        return $this->container->get('language')->translate($key, $tab, $scope);
    }

    public function getAttributeById(string $attributeId): ?Entity
    {
        return $this->getEntityManager()->getEntity('Attribute', $attributeId);
    }

    public function getTypeForAttribute(string $attributeType, ?string $attributeValue): string
    {
        if ($attributeValue == null) {
            $attributeValue = 'value';
        }

        if ($attributeValue === 'id') {
            return 'varchar';
        }

        if ($attributeValue === 'value' && in_array($attributeType, ['int', 'float', 'rangeInt', 'rangeFloat', 'varchar'])) {
            return 'valueWithUnit';
        }

        if ($attributeValue === 'valueUnit') {
            return 'unit';
        }

        if ($attributeType === 'rangeInt') {
            return 'int';
        }

        if ($attributeType === 'rangeFloat') {
            return 'float';
        }

        return $attributeType;
    }

    public function getTypeForField(string $entityName, ?string $field): string
    {
        if( $field === null) return 'varchar';

        $fieldDefs = $this->getMetadata()->get(['entityDefs', $entityName, 'fields', $field]);
        $type = $fieldDefs['type'] ?? 'varchar';
        if (!empty($fieldDefs['unitField'])) {
            $type = 'valueWithUnit';
        }
        return $type;
    }
}
