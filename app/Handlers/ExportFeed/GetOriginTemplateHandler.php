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
use Atro\Core\Http\Response\JsonResponse;
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/ExportFeed/action/getOriginTemplate',
    methods: [
        'GET',
    ],
    summary: 'Get origin export template',
    description: 'Returns the content of the specified export template file.',
    tag: 'ExportFeed',
    parameters: [
        ['name' => 'template', 'in' => 'query', 'required' => true, 'schema' => [
            'type' => 'string',
        ]],
    ],
    responses: [
        200 => ['description' => 'Template content', 'content' => ['application/json' => ['schema' => ['type' => 'object', 'properties' => ['template' => [
            'type' => 'string',
            'nullable' => true,
        ]]]]]],
        400 => [
            'description' => 'template is required',
        ],
        403 => [
            'description' => 'Forbidden',
        ],
    ],
)]
class GetOriginTemplateHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!$this->getAcl()->check('ExportFeed', 'read')) {
            throw new Forbidden();
        }

        $qp       = $request->getQueryParams();
        $template = $qp['template'] ?? '';

        if (empty($template)) {
            throw new BadRequest('template is required');
        }

        return new JsonResponse(['template' => $this->getRecordService('ExportFeed')->getOriginTemplate($template)]);
    }
}
