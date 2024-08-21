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

class BoolType extends AbstractType
{
    public function getSelectColumn(array $configuration): string
    {
        $selectColumn = parent::getSelectColumn($configuration);
        if (!empty($configuration['attributeValue']) && $configuration['attributeValue'] === 'value') {
            $selectColumn = 'bool_value';
        }

        return $selectColumn;
    }

    public function convertToString(array &$result, array $record, array $configuration): void
    {
        $field = $configuration['field'];
        $column = $configuration['column'];

        if ($record[$field] === null) {
            $result[$column] = $configuration['nullValue'];
        } else {
            $result[$column] = !empty($record[$field]) ? 'TRUE' : 'FALSE';
        }
    }
}
