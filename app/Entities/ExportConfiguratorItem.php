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

namespace Export\Entities;

use Atro\Core\Templates\Entities\Base;

class ExportConfiguratorItem extends Base
{
    protected $entityType = "ExportConfiguratorItem";

    public function _hasFileNameTemplate(): bool
    {
        return true;
    }

    public function _hasExportStaticListLabel(): bool
    {
        return true;
    }

    public function _getFileNameTemplate()
    {
        return $this->getVirtualField('fileNameTemplate');
    }

    public function _setFileNameTemplate(?string $fileNameTemplate): void
    {
        $this->setVirtualField('fileNameTemplate', $fileNameTemplate);
    }

    public function _getExportStaticListLabel(): ?bool {
        return !empty($this->getVirtualField('exportStaticListLabel'));
    }

    public function _setExportStaticListLabel(?bool $exportStaticListLabel): void
    {
        $this->setVirtualField('exportStaticListLabel', $exportStaticListLabel);
    }

    public function getVirtualField(string $name)
    {
        if ($this->get('virtualFields') !== null && property_exists($this->get('virtualFields'), $name)) {
            return $this->get('virtualFields')->$name;
        }

        return null;
    }

    public function setVirtualField(string $name, $value): void
    {
        if (empty($this->get('virtualFields'))) {
            $this->set('virtualFields', new \stdClass());
        }

        $this->get('virtualFields')->$name = $value;
    }
}
