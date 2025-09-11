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
            $data['entityDefs']['ExportConfiguratorItem']['fields']['entityAttribute'] = [
                'type' => 'link'
            ];
            $data['entityDefs']['ExportConfiguratorItem']['links']['entityAttribute'] = [
                'type'   => 'belongsTo',
                'entity' => 'Attribute'
            ];
        }

        if (!empty($data['clientDefs']['ExportFeed']['relationshipPanels']['configuratorItems'])) {
            $data['clientDefs']['ExportFeed']['relationshipPanels']['configuratorItems']['dragDrop']['maxSize'] = $this->getConfig()->get('recordsPerPageSmall', 20);
        }

        foreach ($this->getMemoryStorage()->get('dynamic_action') ?? [] as $action) {
            if ($action['type'] === 'export' && !empty($action['source_entity']) && !empty($action['usage'])) {
                $params = [
                    'acl' => [
                        'scope'  => 'ExportFeed',
                        'action' => 'read',
                    ]
                ];

                $defsKey = "dynamic" . ucfirst($action['usage']) . "Actions";

                foreach ($data['clientDefs'][$action['source_entity']][$defsKey] ?? [] as $key => $recordAction) {
                    if ($recordAction['id'] === $action['id']) {
                        $data['clientDefs'][$action['source_entity']][$defsKey][$key] = array_merge($recordAction, $params);
                        break;
                    }
                }
            }
        }

        $data['entityDefs']['Action']['fields']['payload']['conditionalProperties']['visible']['conditionGroup'][0]['type'] = 'in';
        $data['entityDefs']['Action']['fields']['payload']['conditionalProperties']['visible']['conditionGroup'][0]['attribute'] = 'type';
        $data['entityDefs']['Action']['fields']['payload']['conditionalProperties']['visible']['conditionGroup'][0]['value'][] = 'export';

        $data['entityDefs']['Action']['fields']['inBackground']['conditionalProperties']['visible']['conditionGroup'][0]['value'][] = 'export';

        if (empty($data['clientDefs']['ScheduledJob']['dynamicLogic']['fields']['maximumHoursToLookBack']['visible']['conditionGroup'][0])) {
            $data['clientDefs']['ScheduledJob']['dynamicLogic']['fields']['maximumHoursToLookBack']['visible']['conditionGroup'][0] = [
                'type'      => 'in',
                'attribute' => 'type',
                'value'     => ['ExportFeed']
            ];
        } else {
            $data['clientDefs']['ScheduledJob']['dynamicLogic']['fields']['maximumHoursToLookBack']['visible']['conditionGroup'][0]['value'][] = 'ExportFeed';
        }

        if (empty($data['clientDefs']['ScheduledJob']['dynamicLogic']['fields']['maximumDaysForJobExist']['visible'])) {
            $data['clientDefs']['ScheduledJob']['dynamicLogic']['fields']['maximumDaysForJobExist']['visible']['conditionGroup'][0] = [
                'type'      => 'in',
                'attribute' => 'type',
                'value'     => ['ExportJobRemove']
            ];
        } else {
            $data['clientDefs']['ScheduledJob']['dynamicLogic']['fields']['maximumDaysForJobExist']['visible']['conditionGroup'][0]['value'][] = 'ExportJobRemove';
        }

        foreach ($data['app']['exportTypes'] ?? [] as $type => $typeData) {
            $data['entityDefs']['ExportFeed']['fields']['type']['options'][] = $type;
            if (!empty($typeData['default'])) {
                $data['entityDefs']['ExportFeed']['fields']['type']['default'] = $type;
            }
            if (!empty($typeData['fileTypeRequired'])) {
                $data['clientDefs']['ExportFeed']['dynamicLogic']['fields']['fileType']['required']['conditionGroup'][0]['type'] = 'in';
                $data['clientDefs']['ExportFeed']['dynamicLogic']['fields']['fileType']['required']['conditionGroup'][0]['attribute'] = 'type';
                $data['clientDefs']['ExportFeed']['dynamicLogic']['fields']['fileType']['required']['conditionGroup'][0]['value'][] = $type;
            }
        }

        $event->setArgument('data', $data);
    }

    protected function getMemoryStorage(): StorageInterface
    {
        return $this->getContainer()->get('memoryStorage');
    }
}
