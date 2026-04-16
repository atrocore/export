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

namespace Export\Handlers\ExportFeed;

use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Http\Response\BoolResponse;
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/ExportFeed/directExportFile',
    methods: [
        'POST',
    ],
    summary: 'Directly export records of an entity to file',
    description: 'Generates an export file directly for the selected records and fields. Only supports simple entity types; for complex types use an Export Feed.',
    tag: 'ExportFeed',
    requestBody: [
        'required' => true,
        'content'  => [
            'application/json' => [
                'schema' => [
                    'type'       => 'object',
                    'required'   => [
                        'fileType',
                        'scope',
                    ],
                    'properties' => [
                        'scope'            => [
                            'type'    => 'string',
                            'example' => 'Product',
                        ],
                        'fileType'         => [
                            'type'    => 'string',
                            'example' => 'csv',
                        ],
                        'fieldList'        => [
                            'type'  => 'array',
                            'items' => [
                                'type' => 'string',
                            ],
                        ],
                        'exportAllField'   => [
                            'type' => 'boolean',
                        ],
                        'entityFilterData' => [
                            'type' => 'object',
                        ],
                    ],
                ],
            ],
        ],
    ],
    responses: [
        200 => [
            'description' => 'Export job created',
            'content'     => [
                'application/json' => [
                    'schema' => [
                        'type' => 'boolean',
                    ],
                ],
            ],
        ],
        400 => [
            'description' => 'fileType or scope is missing, or neither fieldList nor exportAllField is provided',
        ],
        403 => [
            'description' => 'Access denied',
        ],
    ],
)]
class DirectExportFileHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $data = $this->getRequestBody($request);

        if (!property_exists($data, 'fileType') || !property_exists($data, 'scope')) {
            throw new BadRequest("'fileType' and 'scope' are required.");
        }

        if (empty($data->exportAllField) && empty($data->fieldList)) {
            throw new BadRequest("'fieldList' or 'exportAllField' is required.");
        }

        return new BoolResponse($this->getRecordService('ExportFeed')->directExportFile($data));
    }
}
