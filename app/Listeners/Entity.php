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

namespace Export\Listeners;

use Atro\Core\EventManager\Event;
use Atro\Listeners\AbstractListener;

class Entity extends AbstractListener
{
    public function beforeGetSelectParams(Event $event): void
    {
        $entityType = $event->getArgument('entityType');
        $params = $event->getArgument('params');

        if (!empty($params['where']) && is_array($params['where'])) {
            foreach ($params['where'] as $k => $item) {
                if (!empty($item['data']['unexported'])) {
                    $exportFeed = $this
                        ->getEntityManager()
                        ->getRepository('ExportFeed')
                        ->get($item['data']['unexported']);

                    if (!empty($exportFeed) && !empty($exportFeed->get('lastTime'))) {
                        $params['where'][$k] = [
                            'type'      => 'between',
                            'attribute' => 'modifiedAt',
                            'value'     => [
                                $exportFeed->get('lastTime'),
                                (new \DateTime())->modify('-5 seconds')->format('Y-m-d H:i:s')
                            ],
                            'dateTime'  => true,
                            'timeZone'  => 'UTC'
                        ];

                        if ($entityType === 'Product') {
                            $params['where'][$k] = $this
                                ->getContainer()
                                ->get('selectManagerFactory')
                                ->create('Product')
                                ->prepareWhereForModifiedAtExpanded($params['where'][$k]);
                        }

                        $event->setArgument('params', $params);
                    }
                }
            }
        }
    }
}
