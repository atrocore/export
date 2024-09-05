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

        $attributeId = null;
        if (!empty($configuration['attributeId'])) {
            $attributeId = $configuration['attributeId'];
        }

        if (empty($attributeId)) {
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
        } else {
            $attribute = $this->convertor->getAttributeById($attributeId);
            $field = $configuration['field'];
            $type = $attribute->get('type');

            $valueFrom = null;
            $valueTo = null;
            $unitResult = '';

            if ($configuration['entity'] === 'ProductAttributeValue') {
                $valueFrom = $record['valueFrom'] ?? $record['value'];
                $valueTo = $record['valueTo'] ?? null;
                $unitResult = $record['valueUnitData']['name'] ?? '';
            } elseif ($configuration['entity'] === 'Product') {
                if (!empty($record[$field])) {
                    list($valueFrom, $valueTo, $unitId) = $this->prepareUnitValue((string)$record[$field], $type);
                    $unitResult = $this->convertor
                        ->convertType(
                            'unit',
                            ["{$field}Id" => $unitId],
                            array_merge($configuration, [
                                    'field'               => $field,
                                    'exportBy'            => ['name'],
                                    'markForNoRelation'   => '',
                                    'prepareUnitValueFnc' => [$this, 'prepareUnitValue']
                                ]
                            ))[$column];
                }
            }

            $valueResult = $this->convertor
                ->convertType($type, [$field => $valueFrom], array_merge($configuration, ['field' => $field]))[$column];
        }

        if (in_array($type, ['rangeFloat', 'rangeInt'])) {
            $type = $type === 'rangeFloat' ? 'float' : 'int';
            if (empty($attributeId)) {
                $valueFromResult = $this->convertor
                    ->convertType($type, $record, array_merge($configuration, ['field' => $field . 'From']))[$column];
                $valueToResult = $this->convertor
                    ->convertType($type, $record, array_merge($configuration, ['field' => $field . 'To']))[$column];
            } else {
                $valueFromResult = $this->convertor->convertType($type, [$field => $valueFrom], array_merge($configuration, ['field' => $field]))[$column];
                $valueToResult = $this->convertor->convertType($type, [$field => $valueTo], array_merge($configuration, ['field' => $field]))[$column];
            }

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

    protected function prepareQueryCallbackForAttribute(QueryBuilder $qb, array $conf, string $alias): void
    {
        $attribute = $this->convertor->getAttributeById($conf['attributeId']);

        $column = 'float_value';
        $column1 = 'float_value1';

        switch ($attribute->get('type')) {
            case 'int':
            case 'rangeInt':
                $column = 'int_value';
                $column1 = 'int_value1';
                break;
            case 'varchar':
                $column = 'varchar_value';
                break;
        }

        if (Converter::isPgSQL($qb->getConnection())) {
            $qb->select("STRING_AGG(COALESCE($alias.$column::text, 'N/A') || '::atro::' || COALESCE($alias.$column1::text, 'N/A') || '::atro::' || COALESCE($alias.reference_value, 'N/A'), ', ')");
        } else {
            $qb->select("GROUP_CONCAT(CONCAT(IFNULL($alias.$column, 'N/A'), '::atro::', IFNULL($alias.$column1, 'N/A'), '::atro::', IFNULL($alias.reference_value, 'N/A')) SEPARATOR ', ')");
        }
    }
}
