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
    path: '/ExportJob/action/exportAgain',
    methods: [
        'POST',
    ],
    summary: 'Retry export job',
    description: 'Retries a failed or canceled export job.',
    tag: 'ExportJob',
    requestBody: ['required' => true, 'content' => ['application/json' => ['schema' => ['type' => 'object', 'required' => [
        'id',
    ], 'properties' => ['id' => [
        'type' => 'string',
    ]]]]]],
    responses: [
        200 => ['description' => 'Export job retried', 'content' => ['application/json' => ['schema' => [
            'type' => 'boolean',
        ]]]],
        400 => [
            'description' => 'id is required',
        ],
    ],
)]
class ExportAgainHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $data = $this->getRequestBody($request);

        if (empty($data->id)) {
            throw new BadRequest();
        }

        return new BoolResponse($this->getRecordService('ExportJob')->exportAgain((string) $data->id));
    }
}
