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

namespace Export\Controllers;

use Atro\Core\Exceptions\BadRequest;
use Espo\Core\Templates\Controllers\Base;
use Slim\Http\Request;

class ExportJob extends Base
{
    public function actionExportAgain($params, $data, Request $request): bool
    {
        if (!$request->isPost() || empty($data->id)) {
            throw new BadRequest();
        }

        return $this->getRecordService()->exportAgain($data->id);
    }

    public function actionResendRequest($params, $data, Request $request): bool
    {
        if (!$request->isPost() || empty($data->id)) {
            throw new BadRequest();
        }

        return $this->getRecordService()->resendRequest($data->id);
    }
}
