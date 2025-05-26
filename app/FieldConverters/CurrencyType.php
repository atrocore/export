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

class CurrencyType extends FloatType
{
    protected string $defaultMask = '{{value}} {{currency}}';

    public function convert(array &$result, array $record, array $configuration): void
    {
        $field = $configuration['field'];
        $column = $configuration['column'];
        $mask = !empty($configuration['mask']) ? $configuration['mask'] : $this->defaultMask;

        $value = null;
        $currency = null;
        $finalValue = null;

        if (isset($record[$field]) && $record[$field] !== null) {
            $currency = $record[$field . 'Currency'];
            $value = (float)$record[$field];

            if ($mask === '{{value}}' || $mask === '{{Value}}') {
                $finalValue = $value;
            } else {
                $finalValue = str_replace(['{{value}}', '{{Value}}', '{{currency}}', '{{Currency}}'], [$value, $value, $currency, $currency], $mask);
            }
        }

        $result[$column] = $finalValue;
    }

    public function convertToString(array &$result, array $record, array $configuration): void
    {
        $field = $configuration['field'];
        $column = $configuration['column'];
        $emptyValue = $configuration['emptyValue'];
        $nullValue = $configuration['nullValue'];
        $decimalMark = $configuration['decimalMark'];
        $thousandSeparator = $configuration['thousandSeparator'];
        $mask = !empty($configuration['mask']) ? $configuration['mask'] : $this->defaultMask;

        $value = $nullValue;
        $currency = $nullValue;
        $finalValue = $nullValue;

        if (isset($record[$field])) {
            if (empty($record[$field]) && $record[$field] != 0) {
                $finalValue = $record[$field] === null ? $nullValue : $emptyValue;
            } else {
                $currency = $record[$field . 'Currency'];
                $value = $this->floatToNumber((float)$record[$field], $decimalMark, $thousandSeparator);
                $finalValue = str_replace(['{{value}}', '{{Value}}', '{{currency}}', '{{Currency}}'], [$value, $value, $currency, $currency], $mask);
            }
        }

        $result[$column] = $finalValue;
    }
}
