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

        $service = $this->getInjection('serviceFactory')->create($value->getEntityType());
        if ($value->getEntityType() === 'ProductAttributeValue' && method_exists($service, 'prepareEntity')) {
            $service->prepareEntity($value, false);

            // the values of the script and alias attributes are calculated by the listeners of the output preparation
            if (in_array($value->get('attributeType'), ['script', 'alias'])) {
                $service->prepareEntityForOutput($value);
            }
        } else {
            $service->prepareEntityForOutput($value);
        }

        return $value;
    }
}
