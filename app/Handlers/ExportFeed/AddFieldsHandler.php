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
    path: '/ExportFeed/{id}/addFields',
    methods: [
        'POST',
    ],
    summary: 'Add fields to export feed',
    description: 'Adds the specified fields to the export feed configurator.',
    tag: 'ExportFeed',
    parameters: [
        [
            'name'        => 'id',
            'in'          => 'path',
            'required'    => true,
            'description' => 'Export feed record ID',
            'schema'      => [
                'type' => 'string',
            ],
        ],
    ],
    requestBody: [
        'required' => true,
        'content'  => [
            'application/json' => [
                'schema' => [
                    'type'       => 'object',
                    'required'   => [
                        'entityName',
                        'fields',
                    ],
                    'properties' => [
                        'entityName' => [
                            'type'        => 'string',
                            'description' => 'Entity name of the target entity of the export feed or sheet',
                        ],
                        'fields' => [
                            'type'        => 'array',
                            'description' => 'List of field codes to add',
                            'items'       => [
                                'type' => 'string',
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
    responses: [
        200 => [
            'description' => 'Fields added',
            'content'     => [
                'application/json' => [
                    'schema' => [
                        'type' => 'boolean',
                    ],
                ],
            ],
        ],
        400 => [
            'description' => 'entityName or fields is required',
        ],
        403 => [
            'description' => 'Access denied',
        ],
    ],
)]
class AddFieldsHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $id = (string) $request->getAttribute('id');

        if (empty($id)) {
            throw new BadRequest("'id' is required.");
        }

        $data = $this->getRequestBody($request);

        if (!property_exists($data, 'fields') || !property_exists($data, 'entityName')) {
            throw new BadRequest("'entityName' and 'fields' are required.");
        }

        return new BoolResponse(
            $this->getRecordService('ExportFeed')->addFields((string) $data->entityName, $id, (array) $data->fields)
        );
    }
}
