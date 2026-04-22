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

namespace Export;

use Atro\Core\EntityTypeHandlers\CreateHandler;
use Atro\Core\EntityTypeHandlers\ListHandler;
use Atro\Core\ModuleManager\AbstractModule;
use Espo\Core\Utils\Util;

/**
 * Class Module
 */
class Module extends AbstractModule
{
    /**
     * @inheritdoc
     */
    public static function getLoadOrder(): int
    {
        return 5140;
    }

    public static function afterUpdate(): void
    {
        Util::removeDir(\Export\Services\AbstractExportType::TMP_DIR);
    }

    public function getEntityTypeHandlerExcludes(): array
    {
        return [
            ListHandler::class   => ['ExportConfiguratorItem'],
            CreateHandler::class => ['ExportConfiguratorItem'],
        ];
    }
}
