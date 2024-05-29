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

class IsImage extends AbstractTwigFilter
{
    public function __construct()
    {
        $this->addDependency('metadata');
    }

    public function filter($value)
    {
        if (empty($value) || !is_object($value) || !($value instanceof Entity) || $value->getEntityType() !== 'File') {
            return false;
        }

        $fileNameParts = explode('.', $value->get("name"));
        $fileExt = strtolower(array_pop($fileNameParts));

        return in_array($fileExt, $this->getInjection('metadata')->get('app.file.image.extensions', []));
    }
}
