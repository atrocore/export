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

namespace Export;

use Atro\Core\ModuleManager\AfterInstallAfterDelete;
use Atro\Core\Utils\Config;
use Export\Seeders\FailedExportTemplateSeeder;

class Event extends AfterInstallAfterDelete
{
    public function afterInstall(): void
    {
        $this->addNavigationItems(['ExportFeed']);

        (new FailedExportTemplateSeeder($this->getConfig(), $this->getDbal()))->run();
    }

    public function afterDelete(): void
    {
        $this->removeNavigationItems(['ExportFeed']);
    }

    protected function getConfig(): Config
    {
        return $this->getContainer()->get('config');
    }

    protected function getDbal(): \Doctrine\DBAL\Connection
    {
        return $this->getContainer()->get('dbal');
    }
}