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

namespace Export\Handlers\ExportConfiguratorItem;

use Atro\Core\Exceptions\NotFound;
use Atro\Core\Http\Response\BoolResponse;
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/ExportConfiguratorItem/{id}',
    methods: ['DELETE'],
    summary: 'Delete export configurator item',
    description: 'Deletes an export configurator item. Pass skip404=1 to suppress NotFound errors.',
    tag: 'ExportConfiguratorItem',
    parameters: [
        ['name' => 'id',      'in' => 'path',  'required' => true,  'schema' => ['type' => 'string']],
        ['name' => 'skip404', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'string']],
    ],
    responses: [
        200 => ['description' => 'Deleted', 'content' => ['application/json' => ['schema' => ['type' => 'boolean']]]],
        404 => ['description' => 'Not found'],
    ],
)]
class DeleteHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $id = (string) $request->getAttribute('id');

        try {
            $this->getRecordService('ExportConfiguratorItem')->deleteEntity($id);
        } catch (NotFound $e) {
            $qp = $request->getQueryParams();
            if (empty($qp['skip404'])) {
                throw $e;
            }
        }

        return new BoolResponse(true);
    }
}
