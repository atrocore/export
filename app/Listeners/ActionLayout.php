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

class ActionLayout extends AbstractLayoutListener
{
    public function detail(Event $event): void
    {
        $result = $event->getArgument('result');

        $encoded = json_encode($result[0]['rows']);

        if (strpos($encoded, '"name":"exportFeed"') === false) {
            $result[0]['rows'][] = [['name' => 'exportFeed'], ['name' => 'contentLanguage']];
        } else if (strpos($encoded, '"name":"contentLanguage"') === false) {
            foreach ($result[0]['rows'] as &$row) {
                foreach ($row as $cell) {
                    if (is_array($cell) && ($cell['name'] ?? null) === 'exportFeed') {
                        $row = [['name' => 'exportFeed'], ['name' => 'contentLanguage']];
                        break;
                    }
                }
            }
            unset($row);
        }

        if (strpos(json_encode($result[0]['rows']), '"name":"payload"') !== false) {
            $result[0]['rows'] = json_decode(str_replace(',[{"name":"payload","fullWidth":true}]', '', json_encode($result[0]['rows'])), true);
        }

        $result[0]['rows'][] = [['name' => 'payload', 'fullWidth' => true]];

        $event->setArgument('result', $result);
    }
}
