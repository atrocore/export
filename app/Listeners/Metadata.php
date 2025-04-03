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

use Atro\Core\EventManager\Event;
use Atro\Core\KeyValueStorages\StorageInterface;
use Atro\Listeners\AbstractListener;

class Metadata extends AbstractListener
{
    public function modify(Event $event): void
    {
        $data = $event->getArgument('data');

        if (isset($data['entityDefs']['Attribute'])) {
            $data['entityDefs']['ExportConfiguratorItem']['fields']['attribute'] = [
                'type' => 'link'
            ];
            $data['entityDefs']['ExportConfiguratorItem']['links']['attribute'] = [
                'type'   => 'belongsTo',
                'entity' => 'Attribute'
            ];
        }

        if (isset($data['entityDefs']['Channel'])) {
            $data['entityDefs']['ExportConfiguratorItem']['fields']['channel'] = [
                'type'    => 'link'
            ];
            $data['entityDefs']['ExportConfiguratorItem']['links']['channel'] = [
                'type'   => 'belongsTo',
                'entity' => 'Channel'
            ];
        }

        if (!empty($data['clientDefs']['ExportFeed']['relationshipPanels']['configuratorItems'])) {
            $data['clientDefs']['ExportFeed']['relationshipPanels']['configuratorItems']['dragDrop']['maxSize'] = $this->getConfig()->get('recordsPerPageSmall', 20);
        }

        foreach ($this->getMemoryStorage()->get('dynamic_action') ?? [] as $action) {
            if ($action['type'] === 'export') {
                $params = [
                    'id'      => $action['id'],
                    'name'    => $action['name'],
                    'display' => $action['display'],
                    'acl'     => [
                        'scope'  => 'ExportFeed',
                        'action' => 'read',
                    ]
                ];

                if ($action['usage'] === 'record' && !empty($action['source_entity']) && !empty($action['target_entity'])) {
                    $data['clientDefs'][$action['source_entity']]['dynamicRecordActions'][] = array_merge($params, [
                        'massAction' => !empty($action['mass_action']),
                    ]);
                }

                if ($action['usage'] === 'entity' && !empty($action['source_entity']) && !empty($action['target_entity'])) {
                    $data['clientDefs'][$action['source_entity']]['dynamicEntityActions'][] = $params;
                }

                if ($action['usage'] === 'field' && !empty($action['source_entity']) && !empty($action['display_field'])) {
                    $data['clientDefs'][$action['source_entity']]['dynamicFieldActions'][] = array_merge($params, [
                        'displayField' => $action['display_field'],
                        'massAction'   => !empty($action['mass_action']),
                    ]);
                }
            }
        }

        $data['clientDefs']['Action']['dynamicLogic']['fields']['sourceEntity']['visible']['conditionGroup'][0]['type'] = 'in';
        $data['clientDefs']['Action']['dynamicLogic']['fields']['sourceEntity']['visible']['conditionGroup'][0]['attribute'] = 'type';
        $data['clientDefs']['Action']['dynamicLogic']['fields']['sourceEntity']['visible']['conditionGroup'][0]['value'][] = 'export';

        $data['clientDefs']['Action']['dynamicLogic']['fields']['payload']['visible']['conditionGroup'][0]['type'] = 'in';
        $data['clientDefs']['Action']['dynamicLogic']['fields']['payload']['visible']['conditionGroup'][0]['attribute'] = 'type';
        $data['clientDefs']['Action']['dynamicLogic']['fields']['payload']['visible']['conditionGroup'][0]['value'][] = 'export';

        $data['clientDefs']['Action']['dynamicLogic']['fields']['inBackground']['visible']['conditionGroup'][0]['value'][] = 'export';

        $data['clientDefs']['Action']['dynamicLogic']['fields']['executeAs']['visible']['conditionGroup'][0]['value'][] = 'export';
        $data['clientDefs']['Action']['dynamicLogic']['fields']['executeAs']['required']['conditionGroup'][0]['value'][] = 'export';

        if (empty($data['clientDefs']['ScheduledJob']['dynamicLogic']['fields']['maximumHoursToLookBack']['visible']['conditionGroup'][0])) {
            $data['clientDefs']['ScheduledJob']['dynamicLogic']['fields']['maximumHoursToLookBack']['visible']['conditionGroup'][0] = [
                'type' => 'in',
                'attribute' => 'type',
                'value' => ['ExportFeed']
            ];
        } else {
            $data['clientDefs']['ScheduledJob']['dynamicLogic']['fields']['maximumHoursToLookBack']['visible']['conditionGroup'][0]['value'][] = 'ExportFeed';
        }

        if (empty($data['clientDefs']['ScheduledJob']['dynamicLogic']['fields']['maximumDaysForJobExist']['visible'])) {
            $data['clientDefs']['ScheduledJob']['dynamicLogic']['fields']['maximumDaysForJobExist']['visible']['conditionGroup'][0] = [
                'type' => 'in',
                'attribute' => 'type',
                'value' => ['ExportJobRemove']
            ];
        } else {
            $data['clientDefs']['ScheduledJob']['dynamicLogic']['fields']['maximumDaysForJobExist']['visible']['conditionGroup'][0]['value'][] = 'ExportJobRemove';
        }

        $event->setArgument('data', $data);
    }

    protected function getMemoryStorage(): StorageInterface
    {
        return $this->getContainer()->get('memoryStorage');
    }
}
