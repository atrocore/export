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

namespace Export\Entities;

use Atro\Core\Templates\Entities\Base;
use Espo\Core\Utils\Json;

class ExportJob extends Base
{
    protected $entityType = "ExportJob";

    public function getData(): array
    {
        return empty($this->get('data')) ? [] : Json::decode(Json::encode($this->get('data')), true);
    }
}
