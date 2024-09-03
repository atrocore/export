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
use Atro\Core\Exceptions\Error;
use Atro\Core\KeyValueStorages\StorageInterface;
use Espo\Core\Container;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Language;
use Espo\Core\Utils\Metadata;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Espo\Services\Record;
use Export\FieldConverters\AbstractType;
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

        return $this->convertType($this->getConfigurationItemType($configuration), $record, $configuration);
    }

    public function createFieldConverter(string $type): AbstractType
    {
        $fieldConverterClass = '\Export\FieldConverters\\' . ucfirst($type) . 'Type';
        if (!class_exists($fieldConverterClass) || !is_a($fieldConverterClass, AbstractType::class, true)) {
            $fieldConverterClass = '\Export\FieldConverters\VarcharType';
        }

        return new $fieldConverterClass($this);
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
            $result[$configuration['column']] = $this->container->get('twig')
                ->renderTemplate((string)$template, $templateData);
            return $result;
        }

        $fieldConverterClass = '\Export\FieldConverters\\' . ucfirst($type) . 'Type';
        if (!class_exists($fieldConverterClass) || !is_a($fieldConverterClass,
                \Export\FieldConverters\AbstractType::class, true)) {
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
        /** @var Language $language */
        $language = $this->container->get('language');
        return $language->translate($key, $tab, $scope);
    }

    public function getAttributeById(string $attributeId): ?Entity
    {
        return $this->getEntityManager()->getEntity('Attribute', $attributeId);
    }

    public function getConfigurationItemType(array $configuration): string
    {
        if (!empty($configuration['attributeId'])) {
            $type = $this->prepareConvertorTypeForAttribute($this->getTypeForAttribute($configuration['attributeId']), $configuration['attributeValue']);
        } else {
            $type = $this->getTypeForField($configuration['entity'], $configuration['field']);
        }

        return $type;
    }

    public function getTypeForField(string $entityName, ?string $field): string
    {
        if ($field === null) {
            return 'varchar';
        }

        $fieldDefs = $this->getMetadata()->get(['entityDefs', $entityName, 'fields', $field]);
        $type = $fieldDefs['type'] ?? 'varchar';
        if (!empty($fieldDefs['unitField'])) {
            $type = 'valueWithUnit';
        }
        return $type;
    }

    public function getTypeForAttribute(string $attributeId): string
    {
        $attribute = $this->getEntityManager()->getEntity('Attribute', $attributeId);
        if (empty($attribute)) {
            throw new Error("Attribute $attributeId does not exists.");
        }

        return $attribute->get('type');
    }

    protected function prepareConvertorTypeForAttribute(string $attributeType, ?string $attributeValue): string
    {
        if ($attributeValue == null) {
            $attributeValue = 'value';
        }

        if ($attributeValue === 'id') {
            return 'varchar';
        }

        if ($attributeValue === 'value'
            && in_array($attributeType, ['int', 'float', 'rangeInt', 'rangeFloat', 'varchar'])) {
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
}
