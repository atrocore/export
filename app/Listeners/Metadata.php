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
use Atro\Listeners\AbstractMetadataListener;
use Doctrine\DBAL\ParameterType;
use Export\Repositories\ExportFeed;

class Metadata extends AbstractMetadataListener
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

        $data['entityDefs']['Action']['fields']['applyToPreselectedRecords']['conditionalProperties']['visible']['conditionGroup'][0]['value'][] = 'reExportFailedJob';

        if (empty($data['entityDefs']['ScheduledJob']['fields']['maximumHoursToLookBack']['conditionalProperties']['visible']['conditionGroup'][0])) {
            $data['entityDefs']['ScheduledJob']['fields']['maximumHoursToLookBack']['conditionalProperties']['visible']['conditionGroup'][0] = [
                'type'      => 'in',
                'attribute' => 'type',
                'value'     => ['ExportFeed']
            ];
        } else {
            $data['entityDefs']['ScheduledJob']['fields']['maximumHoursToLookBack']['conditionalProperties']['visible']['conditionGroup'][0]['value'][] = 'ExportFeed';
        }

        if (empty($data['entityDefs']['ScheduledJob']['fields']['maximumDaysForJobExist']['conditionalProperties']['visible'])) {
            $data['entityDefs']['ScheduledJob']['fields']['maximumDaysForJobExist']['conditionalProperties']['visible']['conditionGroup'][0] = [
                'type'      => 'in',
                'attribute' => 'type',
                'value'     => ['ExportJobRemove']
            ];
        } else {
            $data['entityDefs']['ScheduledJob']['fields']['maximumDaysForJobExist']['conditionalProperties']['visible']['conditionGroup'][0]['value'][] = 'ExportJobRemove';
        }

        foreach ($data['app']['exportTypes'] ?? [] as $type => $typeData) {
            $data['entityDefs']['ExportFeed']['fields']['type']['options'][] = $type;
            if (!empty($typeData['default'])) {
                $data['entityDefs']['ExportFeed']['fields']['type']['default'] = $type;
            }
            if (!empty($typeData['fileTypeRequired'])) {
                $data['entityDefs']['ExportFeed']['fields']['fileType']['conditionalProperties']['required']['conditionGroup'][0]['type'] = 'in';
                $data['entityDefs']['ExportFeed']['fields']['fileType']['conditionalProperties']['required']['conditionGroup'][0]['attribute'] = 'type';
                $data['entityDefs']['ExportFeed']['fields']['fileType']['conditionalProperties']['required']['conditionGroup'][0]['value'][] = $type;
            }
        }


        // add connection if configured
        if (!empty($data['scopes']['ExportFeed']['connectionTypes'])) {
            $data['entityDefs']['ExportFeed']['fields']['connection'] = [
                'type'                  => 'link',
                'view'                  => 'export:views/export-feed/fields/connection',
                'conditionalProperties' => [
                    'visible'  => [
                        'conditionGroup' => [
                            [
                                'type'      => 'in',
                                'attribute' => 'type',
                                'value'     => array_keys($data['scopes']['ExportFeed']['connectionTypes'])
                            ]
                        ]
                    ],
                    'required' => [
                        'conditionGroup' => [
                            [
                                'type'      => 'in',
                                'attribute' => 'type',
                                'value'     => []
                            ]
                        ]
                    ]
                ]
            ];

            $data['entityDefs']['ExportFeed']['links']['connection'] = [
                'type'    => 'belongsTo',
                'entity'  => 'Connection',
                'foreign' => 'exportFeeds'
            ];

            $data['entityDefs']['Connection']['links']['exportFeeds'] = [
                'type'    => 'hasMany',
                'entity'  => 'ExportFeed',
                'foreign' => 'connection'
            ];
        }

        $this->prepareHeaderFields($data);

        $event->setArgument('data', $data);
    }

    /**
     * One headerText{k}/headerProperty{k} field pair per header row, for k = 1..(the largest
     * "Number of Headers" configured on any ExportFeed) - see Export\Repositories\ExportFeed,
     * which clears the metadata cache when a feed's value exceeds scopes.ExportFeed.maxNumberOfHeaders,
     * so these fields get regenerated (with the new max) on the next request.
     */
    protected function prepareHeaderFields(array &$data): void
    {
        $max = $this->computeMaxNumberOfHeaders();

        $data['scopes']['ExportFeed']['maxNumberOfHeaders'] = $max;

        $options = $this->computeHeaderPropertyOptions($data);

        for ($k = 1; $k <= $max; $k++) {
            $visible = [
                'conditionGroup' => [
                    ['type' => 'greaterThanOrEquals', 'attribute' => 'exportFeedNumberOfHeaders', 'value' => $k],
                ],
            ];

            $data['entityDefs']['ExportConfiguratorItem']['fields']["headerText$k"] = [
                'type'                  => 'varchar',
                'notStorable'           => true,
                'dataField'             => true,
                'tooltip'               => true,
                'view'                  => 'export:views/export-configurator-item/fields/header-text',
                'conditionalProperties' => [
                    // headerText{k} (the literal custom-text input) is only meaningful for 'custom' -
                    // allAttributes items never allow 'custom' (see header-property.js), so hide it there.
                    'visible'  => [
                        'conditionGroup' => array_merge($visible['conditionGroup'], [
                            ['type' => 'notEquals', 'attribute' => 'type', 'value' => 'allAttributes'],
                        ]),
                    ],
                    'readOnly' => [
                        'conditionGroup' => [
                            ['type' => 'notEquals', 'attribute' => "headerProperty$k", 'value' => 'custom'],
                        ],
                    ],
                ],
            ];

            $data['entityDefs']['ExportConfiguratorItem']['fields']["headerProperty$k"] = [
                'type'                  => 'enum',
                'required'              => true,
                'notStorable'           => true,
                'dataField'             => true,
                'view'                  => 'export:views/export-configurator-item/fields/header-property',
                'options'               => $options,
                'default'               => 'custom',
                'conditionalProperties' => ['visible' => $visible],
            ];
        }
    }

     protected function computeHeaderPropertyOptions(array $data): array
    {
        $options = ['id','custom', 'name', 'code', 'tooltipText'];

        $attributePropertyTypes = ['varchar', 'text', 'enum', 'link', 'linkMultiple'];
        foreach ($data['entityDefs']['Attribute']['fields'] ?? [] as $field => $defs) {
            if (empty($defs['notStorable']) && in_array($defs['type'] ?? null, $attributePropertyTypes, true)) {
                $options[] = $field;
            }
        }

        return array_values(array_unique($options));
    }

     protected function computeMaxNumberOfHeaders(): int
    {
        $cached = $this->getDataManager()->getCacheData(ExportFeed::MAX_NUMBER_OF_HEADERS_CACHE_KEY);
        if (is_int($cached) && $cached >= 1) {
            return $cached;
        }

        try {
            $max = (int)$this->getDbal()->createQueryBuilder()
                ->select('MAX(number_of_headers)')
                ->from('export_feed')
                ->where('deleted = :false')
                ->setParameter('false', false, ParameterType::BOOLEAN)
                ->fetchOne();
        } catch (\Throwable $e) {
            $max = 1;
        }

        $max = max(1, $max);

        $this->getDataManager()->setCacheData(ExportFeed::MAX_NUMBER_OF_HEADERS_CACHE_KEY, $max);

        return $max;
    }
}
