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
use Atro\Core\Container;
use Atro\Core\Utils\Config;
use Atro\Core\Utils\Language;
use Atro\Core\Utils\Metadata;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Espo\Services\Record;
use Export\FieldConverters\AbstractType;

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

        if (!empty($configuration['entityAttributeId']) && !empty($record['_entity']->rowData)
            && empty($record['_entity']->rowData[$configuration['field'] . 'AvId'])) {
            $result[$configuration['column']] = $configuration['markForUnlinkedAttribute'];
        } else {
            $fieldConverter->convertToString($result, $record, $configuration);
        }


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

    public function getConfigurationItemType(array $configuration): string
    {
        return $this->getTypeForField($configuration['entity'], $configuration['field'] ?? null);
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

        if ($type === 'link' && !empty($fieldDefs['entity']) && $fieldDefs['entity'] === 'Unit') {
            $type = 'unit';
        }

        return $type;
    }
}
