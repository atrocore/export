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
use Atro\Core\Http\Response\JsonResponse;
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/ExportFeed/verifyFeedByCode',
    methods: [
        'GET',
    ],
    summary: 'Verify that export feed is correctly configured and contains ID column',
    description: 'Verify that export feed is correctly configured and contains ID column.',
    tag: 'ExportFeed',
    parameters: [
        [
            'name'     => 'code',
            'in'       => 'query',
            'required' => true,
            'schema'   => [
                'type' => 'string',
            ],
        ],
    ],
    responses: [
        200 => [
            'description' => 'A human-readable status message: the code does not match any export feed, the current user does not have access to it, or the feed is (or is not) correctly configured with an ID column.',
            'content'     => [
                'application/json' => [
                    'schema' => [
                        'type'       => 'object',
                        'properties' => [
                            'message' => [
                                'type'        => 'string',
                                'description' => 'One of: an invalid-code message, a no-access message, or the feed\'s configuration status.',
                            ],
                        ],
                    ],
                ],
            ],
        ],
        400 => [
            'description' => "'code' is required",
        ],
    ],
)]
class VerifyFeedByCodeHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $code = $request->getQueryParams()['code'] ?? '';

        if (empty($code)) {
            throw new BadRequest("'code' is required.");
        }

        return new JsonResponse(['message' => $this->getRecordService('ExportFeed')->verifyFeedByCode($code)]);
    }
}
