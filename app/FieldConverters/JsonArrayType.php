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

class JsonArrayType extends AbstractType
{
    public function convertToString(array &$result, array $record, array $configuration): void
    {
        $field = $configuration['field'];
        $column = $configuration['column'];
        $emptyValue = $configuration['emptyValue'];
        $nullValue = $configuration['nullValue'];
        $delimiter = $configuration['delimiter'];

        $result[$column] = $nullValue;

        if (isset($record[$field])) {
            if (empty($record[$field])) {
                $result[$column] = $record[$field] === null ? $nullValue : $emptyValue;
            } elseif (is_array($record[$field])) {
                $values = array_map(
                    fn($value) => is_scalar($value) ? (string)$value : (string)@json_encode($value),
                    $record[$field]
                );
                $result[$column] = implode($delimiter, $values);
            }
        }
    }
}
