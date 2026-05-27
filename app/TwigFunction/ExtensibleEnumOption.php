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

class ExtensibleEnumOption extends AbstractTwigFunction
{
    public function __construct()
    {
        $this->addDependency('serviceFactory');
        $this->addDependency('metadata');
    }

    public function run($extensibleEnumOptionId)
    {
        if (empty($extensibleEnumOptionId) || !$this->getInjection('metadata')->get("scopes.ExtensibleEnumOption.type")) {
            return null;
        }

        try {
            $entity = $this->getInjection('serviceFactory')->create('ExtensibleEnumOption')->getEntity($extensibleEnumOptionId);
        } catch (\Throwable $e) {
            return null;
        }

        return $entity;
    }

}
