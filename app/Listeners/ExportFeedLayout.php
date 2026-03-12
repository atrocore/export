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
use Espo\Core\Utils\Json;
use Atro\Core\EventManager\Event;
use Atro\Listeners\AbstractListener;

class ExportFeedLayout extends AbstractLayoutListener
{
    public function detail(Event $event): void
    {
        $result = $event->getArgument('result');

        if (!empty($this->getMetadata()->get(['scopes', 'ExportFeed', 'connectionTypes']))) {
            $result[1]['rows'][] = [['name' => 'connection'], false];
        }

        $event->setArgument('result', $result);
    }
}
