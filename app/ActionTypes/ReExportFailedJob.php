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

namespace Export\ActionTypes;

use Atro\ActionTypes\AbstractBulkAction;
use Espo\ORM\Entity;

class ReExportFailedJob extends AbstractBulkAction
{
    protected function processEntity(Entity $entity, Entity $log): bool
    {
        return $this->getServiceFactory()->create('ExportJob')->exportAgain($entity->get('id'));
    }
}
