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
    path: '/ExportFeed/action/exportData',
    methods: ['GET'],
    summary: 'Get exported data',
    description: 'Returns exported data for the export feed identified by code.',
    tag: 'ExportFeed',
    parameters: [
        ['name' => 'code',   'in' => 'query', 'required' => true,  'schema' => ['type' => 'string']],
        ['name' => 'offset', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'integer', 'nullable' => true]],
    ],
    responses: [
        200 => ['description' => 'Exported data', 'content' => ['application/json' => ['schema' => ['type' => 'object', 'properties' => ['total' => ['type' => 'integer'], 'urlColumns' => ['type' => 'array', 'items' => ['type' => 'string']], 'records' => ['type' => 'array', 'items' => ['type' => 'object']]]]]]],
        400 => ['description' => 'code is required'],
        403 => ['description' => 'Forbidden'],
    ],
)]
class ExportDataHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!$this->getAcl()->check('ExportFeed', 'read')) {
            throw new Forbidden();
        }

        $qp   = $request->getQueryParams();
        $code = $qp['code'] ?? '';

        if (empty($code)) {
            throw new BadRequest('code is required');
        }

        $offset = isset($qp['offset']) ? (int) $qp['offset'] : null;

        return new JsonResponse($this->getRecordService('ExportFeed')->getData($code, $offset));
    }
}
