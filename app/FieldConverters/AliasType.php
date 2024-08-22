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

class AliasType extends AbstractType
{
    public function convertToString(array &$result, array $record, array $configuration): void
    {
        $column = $configuration['column'];

        $result[$column] = $configuration['nullValue'];

        $pavId = $record[$configuration['field']] ?? null;

        if (!empty($pavId)) {
            // @todo this should be optimized
            $pav = $this->convertor->getService('ProductAttributeValue')->getEntity($pavId);

            $pavResults = [];
            foreach ($pav->get('valueOptionsData') as $item) {
                if (is_array($item['name'])) {
                    $pavResults[] = implode(', ', $item['name']);
                } else {
                    $pavResults[] = $item['name'];
                }
            }

            $result[$column] = implode(' | ', $pavResults);
        }
    }
}
