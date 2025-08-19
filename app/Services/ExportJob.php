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

namespace Export\Services;

use Atro\Core\Templates\Services\Base;
use Espo\ORM\Entity;

class ExportJob extends Base
{
    protected $mandatorySelectAttributeList = ['exportFeedId', 'exportFeedName', 'state', 'stateMessage', 'data'];

    public function prepareEntityForOutput(Entity $entity)
    {
        parent::prepareEntityForOutput($entity);

        // by design export job always has exported file, but for special cases system does not have what to export, for example system sends DELETE http request without body
        if ($entity->get('fileName') === 'empty.txt') {
            $entity->set('fileId', null);
            $entity->set('fileName', null);
        }
    }
}
