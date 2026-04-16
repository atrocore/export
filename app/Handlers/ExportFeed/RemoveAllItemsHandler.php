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
    path: '/ExportFeed/{id}/removeAllItems',
    methods: [
        'POST',
    ],
    summary: 'Remove all configurator items from export feed',
    description: 'Removes all configurator items for the specified entity type from the export feed.',
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
                        'entityType',
                    ],
                    'properties' => [
                        'entityType' => [
                            'type'        => 'string',
                            'description' => 'Entity type of the configurator items to remove',
                        ],
                    ],
                ],
            ],
        ],
    ],
    responses: [
        200 => [
            'description' => 'All items removed',
            'content'     => [
                'application/json' => [
                    'schema' => [
                        'type' => 'boolean',
                    ],
                ],
            ],
        ],
        400 => [
            'description' => "'entityType' is required",
        ],
        403 => [
            'description' => 'Access denied',
        ],
    ],
)]
class RemoveAllItemsHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $id   = (string) $request->getAttribute('id');
        $data = $this->getRequestBody($request);

        if (!property_exists($data, 'entityType')) {
            throw new BadRequest("'entityType' is required.");
        }

        return new BoolResponse(
            $this->getRecordService('ExportFeed')->removeAllItems((string) $data->entityType, $id)
        );
    }
}
