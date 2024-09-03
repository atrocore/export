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
use Espo\Core\Utils\Util;

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

    public static function afterUpdate(): void
    {
        Util::removeDir(\Export\Services\AbstractExportType::TMP_DIR);
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
    }
}
