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
use Espo\ORM\Entity as OrmEntity;

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

    public function putAclMetaForLink(OrmEntity $entityFrom, string $link, OrmEntity $entity): void
    {
        if ($entityFrom->getEntityName() !== 'ExportFeed' || $link !== 'exportJobs') {
            parent::putAclMetaForLink($entityFrom, $link, $entity);
            return;
        }

        parent::putAclMetaForLink($entityFrom, $link, $entity);

        $condition = in_array($entity->get('state'), ['Failed', 'Canceled'])
            && ($this->getUser()->isAdmin() ?? $this->getAcl()->check($entity, 'edit'));

        $entity->setMetaPermission('tryAgainExportJob', $condition);
    }

    public function exportAgain(string $id): bool
    {
        $entity  = $this->getEntity($id);

        if(!in_array($entity->get('state'), ['Failed', 'Canceled'])) {
            return false;
        }

        $entity->set('state', 'Pending');
        $this->getEntityManager()->saveEntity($entity);

        return true;
    }

    public function resendRequest(string $id): bool
    {
        $entity  = $this->getEntity($id);

        if(!in_array($entity->get('state'), ['Failed', 'Canceled'])) {
            return false;
        }

        $entity->set('state', 'Pending');
        $entity->set('shouldResend', true);
        $this->getEntityManager()->saveEntity($entity);

        return true;
    }
}
