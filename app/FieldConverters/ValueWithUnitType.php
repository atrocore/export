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

use Atro\Core\Utils\Database\DBAL\Schema\Converter;
use Doctrine\DBAL\Query\QueryBuilder;

class ValueWithUnitType extends AbstractType
{
    private $emptyValue;
    private $nullValue;

    public function convertToString(array &$result, array $record, array $configuration): void
    {
        $column = $configuration['column'];
        $this->emptyValue = $configuration['emptyValue'];
        $this->nullValue = $configuration['nullValue'];


        $fieldDefs = $this
            ->getMetadata()
            ->get(['entityDefs', $configuration['entity'], 'fields', $configuration['field']]);
        // use main field instead
        $field = $fieldDefs['mainField'];
        $type = $this->getMetadata()->get(['entityDefs', $configuration['entity'], 'fields', $field, 'type']);
        $valueResult = $this
            ->convertor
            ->convertType($type, $record, array_merge($configuration, ['field' => $field]))[$column];
        $unitResult = $record[$field . 'UnitName'] ?? '';

        if (in_array($type, ['rangeFloat', 'rangeInt'])) {
            $type = $type === 'rangeFloat' ? 'float' : 'int';

            $valueFromResult = $this->convertor
                ->convertType($type, $record, array_merge($configuration, ['field' => $field . 'From']))[$column];
            $valueToResult = $this->convertor
                ->convertType($type, $record, array_merge($configuration, ['field' => $field . 'To']))[$column];


            $result[$column] = "";

            if (!$this->isNullorEmptyResult($valueFromResult) && !$this->isNullorEmptyResult($valueToResult)) {
                $result[$column] = "$valueFromResult - $valueToResult";
            } else {
                if (!$this->isNullorEmptyResult($valueFromResult)) {
                    $result[$column] = ">= $valueFromResult";
                } else {
                    if (!$this->isNullorEmptyResult($valueToResult)) {
                        $result[$column] = "<= $valueToResult";
                    }
                }
            }
        } else {
            $result[$column] = "$valueResult";
        }

        if (!empty($unitResult)) {
            $result[$column] .= " $unitResult";
        }
    }

    public function isNullorEmptyResult(string $result = null): bool
    {
        return in_array($result, [$this->nullValue, $this->emptyValue]);
    }

    public function prepareUnitValue(string $value, string $type = 'varchar'): array
    {
        $valueParts = explode('::atro::', $value);

        $valueFrom = $valueParts[0] === 'N/A' ? null : $valueParts[0];
        if ($valueFrom !== null) {
            switch ($type) {
                case 'float':
                    $valueFrom = (float)$valueFrom;
                    break;
                case 'int':
                    $valueFrom = (int)$valueFrom;
                    break;
            }
        }

        $valueTo = !isset($valueParts[1]) || $valueParts[1] === 'N/A' ? null : $valueParts[1];
        if ($valueTo !== null) {
            switch ($type) {
                case 'float':
                    $valueTo = (float)$valueTo;
                    break;
                case 'int':
                    $valueTo = (int)$valueTo;
                    break;
            }
        }

        return [
            $valueFrom,
            $valueTo,
            !isset($valueParts[2]) || $valueParts[2] === 'N/A' ? null : $valueParts[2]
        ];
    }
}
