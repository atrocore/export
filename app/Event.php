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
use Espo\Core\Utils\Config;

class Event extends AfterInstallAfterDelete
{
    public function afterInstall(): void
    {
        /** @var Config $config */
        $config = $this->getContainer()->get('config');
        $config->set('exportJobsMaxDays', 21);

        $tabList = $config->get("tabList", []);
        if (!in_array('ExportFeed', $tabList)) {
            $tabList[] = 'ExportFeed';
        }

        $config->set('tabList', $tabList);
        $config->save();
    }

    public function afterDelete(): void
    {
        /** @var Config $config */
        $config = $this->getContainer()->get('config');

        $tabList = [];
        foreach ($config->get("tabList", []) as $tab) {
            if ($tab != 'ExportFeed') {
                $tabList[] = $tab;
            }
        }

        $config->set('tabList', $tabList);
        $config->save();
    }
}