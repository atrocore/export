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

class ArrayType extends AbstractType
{
    public function getSelectColumn(array $configuration): string
    {
        $selectColumn = parent::getSelectColumn($configuration);
        if (!empty($configuration['attributeValue']) && $configuration['attributeValue'] === 'value') {
            $selectColumn = 'text_value';
        }

        return $selectColumn;
    }

    public function convertToString(array &$result, array $record, array $configuration): void
    {
        $field = $configuration['field'];
        $column = $configuration['column'];
        $emptyValue = $configuration['emptyValue'];
        $nullValue = $configuration['nullValue'];
        $delimiter = $configuration['delimiter'];

        $result[$column] = $nullValue;
        if (isset($record[$field])) {
            if (!empty($configuration['attributeId'])) {
                $record[$field] = @json_decode($record[$field], true);
            }
            if (empty($record[$field])) {
                $result[$column] = $record[$field] === null ? $nullValue : $emptyValue;
            } else {
                if (is_array($record[$field])) {
                    $result[$column] = implode($delimiter, $record[$field]);
                }
            }
        }
    }
}
