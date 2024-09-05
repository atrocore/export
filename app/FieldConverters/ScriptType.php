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

namespace Export\FieldConverters;

class ScriptType extends AbstractType
{
    public function convertToString(array &$result, array $record, array $configuration): void
    {
        $column = $configuration['column'];

        $result[$column] = $configuration['nullValue'];

        $pavId = null;

        if ($configuration['entity'] === 'ProductAttributeValue') {
            $pavId = $record['id'];
        } elseif ($configuration['entity'] === 'Product') {
            $pavId = $record[$configuration['field']] ?? null;
        }

        if (!empty($pavId)) {
            // @todo this should be optimized
            $pav = $this->convertor->getService('ProductAttributeValue')->getEntity($pavId);

            $result[$column] = $pav->get('value');
        }
    }
}
