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

namespace Export\Handlers\ExportJob;

use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Http\Response\BoolResponse;
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/ExportJob/{id}/exportAgain',
    methods: [
        'POST',
    ],
    summary: 'Retry export job',
    description: 'Retries a failed or canceled export job.',
    tag: 'ExportJob',
    parameters: [
        [
            'name'        => 'id',
            'in'          => 'path',
            'required'    => true,
            'description' => 'Export job record ID',
            'schema'      => [
                'type' => 'string',
            ],
        ],
    ],
    responses: [
        200 => [
            'description' => 'Export job retried',
            'content'     => [
                'application/json' => [
                    'schema' => [
                        'type' => 'boolean',
                    ],
                ],
            ],
        ],
        403 => [
            'description' => 'Access denied',
        ],
        404 => [
            'description' => 'Export job not found',
        ],
    ],
)]
class ExportAgainHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $id = (string) $request->getAttribute('id');

        return new BoolResponse($this->getRecordService('ExportJob')->exportAgain($id));
    }
}
