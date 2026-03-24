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

use Atro\Core\EntityTypeHandlers\CreateLinkHandler;
use Atro\Core\EntityTypeHandlers\FollowHandler;
use Atro\Core\EntityTypeHandlers\GetDuplicateAttributesHandler;
use Atro\Core\EntityTypeHandlers\ListHandler;
use Atro\Core\EntityTypeHandlers\ListLinkedHandler;
use Atro\Core\EntityTypeHandlers\MassDeleteHandler;
use Atro\Core\EntityTypeHandlers\MassFollowHandler;
use Atro\Core\EntityTypeHandlers\MassUnfollowHandler;
use Atro\Core\EntityTypeHandlers\MassUpdateHandler;
use Atro\Core\EntityTypeHandlers\MergeHandler;
use Atro\Core\EntityTypeHandlers\RemoveLinkHandler;
use Atro\Core\EntityTypeHandlers\UnfollowHandler;
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
            // ExportConfiguratorItem — managed exclusively via ExportFeed; direct CRUD is not allowed
            ListHandler::class                   => ['ExportConfiguratorItem'],
            ListLinkedHandler::class             => ['ExportConfiguratorItem'],
            MassUpdateHandler::class             => ['ExportConfiguratorItem'],
            MassDeleteHandler::class             => ['ExportConfiguratorItem'],
            CreateLinkHandler::class             => ['ExportConfiguratorItem'],
            RemoveLinkHandler::class             => ['ExportConfiguratorItem'],
            FollowHandler::class                 => ['ExportConfiguratorItem'],
            UnfollowHandler::class               => ['ExportConfiguratorItem'],
            MergeHandler::class                  => ['ExportConfiguratorItem'],
            GetDuplicateAttributesHandler::class => ['ExportConfiguratorItem'],
            MassFollowHandler::class             => ['ExportConfiguratorItem'],
            MassUnfollowHandler::class           => ['ExportConfiguratorItem'],
        ];
    }
}
