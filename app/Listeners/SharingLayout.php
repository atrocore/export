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

namespace Export\Listeners;

use Atro\Listeners\AbstractLayoutListener;
use Atro\Core\EventManager\Event;

class SharingLayout extends AbstractLayoutListener
{
    protected function detail(Event $event): void
    {
        $result = $event->getArgument('result');

        if (strpos(json_encode($result[0]['rows']), '"name":"exportFeed"') === false) {
            $result[0]['rows'][] = [['name' => 'exportFeed'], false];
        }

        $event->setArgument('result', $result);
    }
}
