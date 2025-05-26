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

use Doctrine\DBAL\Query\QueryBuilder;

class IntType extends AbstractType
{
    public function convertToString(array &$result, array $record, array $configuration): void
    {
        $field = $configuration['field'];
        $column = $configuration['column'];
        $emptyValue = $configuration['emptyValue'];
        $nullValue = $configuration['nullValue'];
        $decimalMark = $configuration['decimalMark'];
        $thousandSeparator = $configuration['thousandSeparator'];

        $result[$column] = $nullValue;
        if (isset($record[$field])) {
            if (empty($record[$field]) && $record[$field] != 0) {
                $result[$column] = $record[$field] === null ? $nullValue : $emptyValue;
            } else {
                $result[$column] = number_format((float)$record[$field], 0, $decimalMark, $thousandSeparator);
            }
        }
    }
}
