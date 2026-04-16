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
    path: '/ExportFeed/{id}/addAttributes',
    methods: [
        'POST',
    ],
    summary: 'Add attributes to export feed',
    description: 'Adds the specified attributes to the export feed configurator.',
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
                        'attributesIds',
                    ],
                    'properties' => [
                        'entityName' => [
                            'type'        => 'string',
                            'description' => 'Entity name of the target entity of the export feed or sheet',
                        ],
                        'attributesIds' => [
                            'type'        => 'array',
                            'description' => 'List of attribute IDs to add',
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
            'description' => 'Attributes added',
            'content'     => [
                'application/json' => [
                    'schema' => [
                        'type' => 'boolean',
                    ],
                ],
            ],
        ],
        400 => [
            'description' => 'entityName or attributesIds is required',
        ],
        403 => [
            'description' => 'Access denied',
        ],
    ],
)]
class AddAttributesHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $id = (string) $request->getAttribute('id');

        if (empty($id)) {
            throw new BadRequest("'id' is required.");
        }

        $data = $this->getRequestBody($request);

        if (!property_exists($data, 'attributesIds') || !property_exists($data, 'entityName')) {
            throw new BadRequest("'entityName' and 'attributesIds' are required.");
        }

        return new BoolResponse(
            $this->getRecordService('ExportFeed')->addAttributes((string) $data->entityName, $id, (array) $data->attributesIds)
        );
    }
}
