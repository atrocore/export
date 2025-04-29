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

namespace Export\TemplateLoaders;

use Atro\Core\KeyValueStorages\StorageInterface;
use Atro\Core\Container;
use Espo\Core\Injectable;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Metadata;

abstract class AbstractTemplate extends Injectable
{
    /** @var string $additionalTemplate */
    protected string $additionalTemplate;

    /** @var string $name */
    protected string $name;

    /** @var string $path */
    protected string $dir;

    /** @var string $entityType */
    protected string $entityType;

    /** @var array $data */
    protected array $data;

    /** @var string $fileType */
    protected string $fileType;

    /** @var string $type */
    protected string $type;

    /**
     * @param Container $container
     */
    public function __construct()
    {
        $this->addDependency('container');
        $this->addDependency('config');
        $this->addDependency('metadata');
        $this->addDependency('memoryStorage');
    }

    /**
     * @param string $template
     *
     * @return void
     */
    public function addTemplate(string $template): void
    {
        $this->additionalTemplate = $template;
    }

    /**
     * @param array $data
     *
     * @return void
     */
    public function setData(array $data): void
    {
        $this->data = $data;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    public function loadTemplateFromFile(): string
    {
        if (!empty($this->dir) && !empty($this->name)) {
            $template = $this->getMemoryStorage()->get($this->name);

            if (!empty($template)) {
                return $template;
            }

            $fullPath = dirname((new \ReflectionClass($this))->getFileName(), 3) . '/' . trim($this->dir, '/') . '/' . $this->name;

            if (file_exists($fullPath) && !empty($result = @file_get_contents($fullPath))) {
                $this->getMemoryStorage()->set($this->name, $result);

                return $result;
            }
        }

        return '';
    }

    /**
     * @return string
     */
    abstract public function render(): string;

    /**
     * @param array $data
     *
     * @return bool
     */
    public function isTemplateCompatible(array $feedData): bool
    {
        if(empty($feedData['entity']) || empty($feedData['fileType']) || empty($feedData['type'])) {
            return false;
        }
        if (!empty($this->entityType) && $this->entityType != $feedData['entity']) {
            return false;
        }

        if (!empty($this->fileType) && $this->fileType != $feedData['fileType']) {
            return false;
        }

        if (!empty($this->type) && $this->type != $feedData['type']) {
            return false;
        }

        return true;
    }

    protected function getContainer(): Container
    {
        return $this->getInjection('container');
    }

    /**
     * @return Config
     */
    protected function getConfig(): Config
    {
        return $this->getInjection('config');
    }

    /**
     * @return Metadata
     */
    protected function getMetadata(): Metadata
    {
        return $this->getInjection('metadata');
    }

    /**
     * @return StorageInterface
     */
    protected function getMemoryStorage(): StorageInterface
    {
        return $this->getInjection('memoryStorage');
    }
}
