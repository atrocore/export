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

use Atro\Core\Http\Response\JsonResponse;
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/ExportFeed/loadAvailableTemplates',
    methods: [
        'POST',
    ],
    summary: 'Load available export templates',
    description: 'Returns export templates available for the given entity and export feed configuration.',
    tag: 'ExportFeed',
    requestBody: [
        'required' => true,
        'content'  => [
            'application/json' => [
                'schema' => [
                    'type' => 'object',
                ],
            ],
        ],
    ],
    responses: [
        200 => [
            'description' => 'Available templates',
            'content'     => [
                'application/json' => [
                    'schema' => [
                        'type'  => 'array',
                        'items' => [
                            'type'       => 'object',
                            'properties' => [
                                'template' => [
                                    'type' => 'string',
                                    'description' => 'Template loader identifier used as the value when selecting a template.'
                                ],
                                'name' => [
                                    'type' => 'string', 'description' => 'Human-readable template display name shown in the UI.'
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
        403 => [
            'description' => 'Access denied',
        ],
    ],
)]
class LoadAvailableTemplatesHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $data = $this->getRequestBody($request);

        $dataArray = json_decode(json_encode($data), true);

        return new JsonResponse($this->getRecordService('ExportFeed')->getAvailableTemplates($dataArray));
    }
}
