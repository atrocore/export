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
    path: '/ExportFeed/{id}/addAllAttributes',
    methods: [
        'POST',
    ],
    summary: 'Add all attributes to export feed',
    description: 'Adds all available attributes for the given entity to the export feed configurator.',
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
                    ],
                    'properties' => [
                        'entityName' => [
                            'type'        => 'string',
                            'description' => 'Entity name of the target entity of the export feed or sheet',
                        ],
                    ],
                ],
            ],
        ],
    ],
    responses: [
        200 => [
            'description' => 'All attributes added',
            'content'     => [
                'application/json' => [
                    'schema' => [
                        'type' => 'boolean',
                    ],
                ],
            ],
        ],
        400 => [
            'description' => 'entityName is required or invalid',
        ],
        403 => [
            'description' => 'Access denied',
        ],
    ],
)]
class AddAllAttributesHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $id = (string) $request->getAttribute('id');

        if (empty($id)) {
            throw new BadRequest("'id' is required.");
        }

        $data = $this->getRequestBody($request);

        if (!property_exists($data, 'entityName')) {
            throw new BadRequest("'entityName' is required.");
        }

        return new BoolResponse(
            $this->getRecordService('ExportFeed')->addAllAttributes((string) $data->entityName, $id)
        );
    }
}
