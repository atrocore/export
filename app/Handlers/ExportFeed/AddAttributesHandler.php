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
use Atro\Core\Exceptions\Forbidden;
use Atro\Core\Http\Response\BoolResponse;
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/ExportFeed/action/addAttributes',
    methods: [
        'POST',
    ],
    summary: 'Add attributes to export feed',
    description: 'Adds the specified attributes to the export feed configurator.',
    tag: 'ExportFeed',
    requestBody: ['required' => true, 'content' => ['application/json' => ['schema' => ['type' => 'object', 'required' => [
        'id',
        'entityName',
        'attributesIds',
    ], 'properties' => ['id' => [
        'type' => 'string',
    ], 'entityName' => [
        'type' => 'string',
    ], 'attributesIds' => ['type' => 'array', 'items' => [
        'type' => 'string',
    ]]]]]]],
    responses: [
        200 => ['description' => 'Attributes added', 'content' => ['application/json' => ['schema' => [
            'type' => 'boolean',
        ]]]],
        400 => [
            'description' => 'Bad request',
        ],
        403 => [
            'description' => 'Forbidden',
        ],
    ],
)]
class AddAttributesHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!$this->getAcl()->check('ExportFeed', 'edit')) {
            throw new Forbidden();
        }

        $data = $this->getRequestBody($request);

        if (!property_exists($data, 'id') || !property_exists($data, 'attributesIds') || !property_exists($data, 'entityName')) {
            throw new BadRequest();
        }

        return new BoolResponse(
            $this->getRecordService('ExportFeed')->addAttributes((string) $data->entityName, (string) $data->id, (array) $data->attributesIds)
        );
    }
}
