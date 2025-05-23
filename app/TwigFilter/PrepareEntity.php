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

namespace Export\TwigFilter;

use Espo\ORM\Entity;

class PrepareEntity extends AbstractTwigFilter
{
    public function __construct()
    {
        $this->addDependency('serviceFactory');
    }

    public function filter($value)
    {
        if (empty($value) || !is_object($value) || !($value instanceof Entity)) {
            return null;
        }

       $this->getInjection('serviceFactory')
            ->create($value->getEntityType())
            ->prepareEntityForOutput($value);

        return $value;
    }
}
