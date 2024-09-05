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

use Atro\Core\OpenApiGenerator;
use Atro\Core\ModuleManager\AbstractModule;

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

    public function prepareApiDocs(array &$data, array $schemas): void
    {
        parent::prepareApiDocs($data, $schemas);

        $data['paths']["/ExportFeed/action/exportFile"]['post'] = [
            'tags'        => ['ExportFeed'],
            "summary"     => "Export data to file",
            "description" => "Export data to file",
            "operationId" => "exportFile",
            'security'    => [['Authorization-Token' => []]],
            'requestBody' => [
                'required' => true,
                'content'  => [
                    'application/json' => [
                        'schema' => [
                            "type"       => "object",
                            "properties" => [
                                "id" => [
                                    "type" => "string",
                                ],
                            ],
                        ]
                    ]
                ],
            ],
            "responses"   => OpenApiGenerator::prepareResponses(["type" => "boolean"]),
        ];

        $data['paths']["/ExportFeed/action/exportChannel"]['post'] = [
            'tags'        => ['ExportFeed'],
            "summary"     => "Export channel data to file",
            "description" => "Export channel data to file",
            "operationId" => "exportChannel",
            'security'    => [['Authorization-Token' => []]],
            'requestBody' => [
                'required' => true,
                'content'  => [
                    'application/json' => [
                        'schema' => [
                            "type"       => "object",
                            "properties" => [
                                "id" => [
                                    "type" => "string",
                                ],
                            ],
                        ]
                    ]
                ],
            ],
            "responses"   => OpenApiGenerator::prepareResponses(["type" => "boolean"]),
        ];

        $data['paths']['/ExportFeed/action/directExportFile']['post'] = [
            'tags'        => ['ExportFeed'],
            "operationId" => "directExportFile",
            "summary" => "Directly Export records of an entity to file without",
            "description" => "The system will run will generate directly an export file of the selected record and the selected fields of the record, this will only support simple type, for more complex type please consider create and appropriate Export feed",
            'security'    => [['Authorization-Token' => []]],
            "requestBody" => [
                "required" => true,
                "content" => [
                    "application/json" => [
                        "schema" => [
                            "type" => "object",
                            "properties" => [
                                "scope" => [
                                    "type" => "string",
                                    "example" => "Product"
                                ],
                                "fileType" => [
                                    "type" => "string",
                                    "example" => "csv"
                                ],
                                "fieldList" => [
                                    "type" => "array",
                                    "items" => [
                                        "type" => "string"
                                    ]
                                ],
                                "entityFilterData" => [
                                    "type" => "object",
                                    "properties" => [
                                        "byWhere" => [
                                            "type" => "boolean"
                                        ],
                                        "selectData" => [
                                            "type" => "object",
                                            "properties" => [
                                                "select" => [
                                                    "type" => "string",
                                                    "example" => "id,name"
                                                ]
                                            ]
                                        ],
                                        "where" => [
                                            "type" => "array",
                                            "items" => [
                                                "type" => "object",
                                                "properties" => [
                                                    "type" => [
                                                        "type" => "string",
                                                        "example" => "isNotNull"
                                                    ],
                                                    "attribute" => [
                                                        "type" => "string",
                                                        "example" => "name"
                                                    ],
                                                    "value" => [
                                                        "anyOf" => ["string", "number", "array", "boolean"]
                                                    ]
                                                ]
                                            ]
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            "responses" => OpenApiGenerator::prepareResponses(["type" => "boolean"])
        ];

    }
}
