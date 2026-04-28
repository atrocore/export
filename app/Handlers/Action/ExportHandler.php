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

namespace Export\Handlers\Action;

use Atro\Core\Routing\Route;
use Atro\Handlers\Action\AbstractActionTypeSyncHandler;

#[Route(
    path: '/Action/{id}/export',
    methods: [
        'POST',
    ],
    summary: 'Execute Export action',
    description: 'Executes the specified Export action synchronously. Runs the configured export feed.',
    tag: 'Action',
    parameters: [
        [
            'name'        => 'id',
            'in'          => 'path',
            'required'    => true,
            'description' => 'Action record ID.',
            'schema'      => [
                'type' => 'string',
            ],
        ],
    ],
    requestBody: [
        'required' => false,
        'content'  => [
            'application/json' => [
                'schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'entityId' => [
                            'type'        => 'string',
                            'description' => 'Limit export scope to a specific entity record (by ID). The feed\'s where filter is overridden with a single-ID constraint.',
                        ],
                        'where'    => [
                            'type'        => 'array',
                            'description' => 'Filter conditions for mass export. Applied as the feed\'s scope filter.',
                            'items'       => ['type' => 'object'],
                        ],
                    ],
                ],
            ],
        ],
    ],
    responses: [
        200 => [
            'description' => 'Execution result.',
            'content'     => [
                'application/json' => [
                    'schema' => [
                        'type'       => 'object',
                        'properties' => [
                            'success' => [
                                'type' => 'boolean',
                            ],
                            'message' => [
                                'type'     => 'string',
                                'nullable' => true,
                            ],
                        ],
                    ],
                ],
            ],
        ],
        404 => [
            'description' => 'Action record not found.',
        ],
    ],
)]
class ExportHandler extends AbstractActionTypeSyncHandler
{
}
