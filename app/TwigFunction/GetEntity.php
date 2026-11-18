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

namespace Export\TwigFunction;

use Atro\Core\ServiceFactory;

class GetEntity extends AbstractTwigFunction
{
    public function run(string $entityType, string $id)
    {
        if (empty($entityType) || empty($id)) {
            return null;
        }

        if (!$this->getServiceFactory()->checkExists($entityType)) {
            return null;
        }

        try {
            $entity = $this->getServiceFactory()->create($entityType)->getEntity($id);
        } catch (\Throwable $e) {
            return null;
        }

        return $entity;
    }

    /**
     * @return ServiceFactory
     */
    protected function getServiceFactory(): ServiceFactory
    {
        return $this->container->get('serviceFactory');
    }
}
