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

use Atro\Core\Exceptions\Forbidden;
use Atro\Core\Http\Response\JsonResponse;
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/ExportFeed',
    methods: ['GET'],
    summary: 'List export feeds',
    description: 'Returns a collection of export feed records. Supports the exportEntity query parameter to filter by target entity.',
    tag: 'ExportFeed',
    parameters: [
        ['name' => 'exportEntity', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'string']],
        ['name' => 'select',       'in' => 'query', 'required' => false, 'schema' => ['type' => 'string']],
        ['name' => 'where',        'in' => 'query', 'required' => false, 'schema' => ['anyOf' => [['type' => 'array'], ['type' => 'object'], ['type' => 'string']]]],
        ['name' => 'offset',       'in' => 'query', 'required' => false, 'schema' => ['type' => 'integer']],
        ['name' => 'maxSize',      'in' => 'query', 'required' => false, 'schema' => ['type' => 'integer']],
        ['name' => 'sortBy',       'in' => 'query', 'required' => false, 'schema' => ['type' => 'string']],
        ['name' => 'asc',          'in' => 'query', 'required' => false, 'schema' => ['type' => 'boolean']],
    ],
    responses: [
        200 => ['description' => 'Collection of export feeds', 'content' => ['application/json' => ['schema' => ['type' => 'object', 'properties' => ['total' => ['type' => 'integer'], 'list' => ['type' => 'array', 'items' => ['type' => 'object']]]]]]],
    ],
)]
class ListHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!$this->getAcl()->check('ExportFeed', 'read')) {
            throw new Forbidden();
        }

        $params = $this->buildListParams($request);

        $qp = $request->getQueryParams();
        if (!empty($qp['exportEntity'])) {
            $params['exportEntity'] = $qp['exportEntity'];
        }

        $result = $this->getRecordService('ExportFeed')->findEntities($params);

        return new JsonResponse($this->buildListResult($result, $params));
    }
}
