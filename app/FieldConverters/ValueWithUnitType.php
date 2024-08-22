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
            $fieldDefs = $this->getMetadata()->get(['entityDefs', $configuration['entity'], 'fields', $configuration['field']]);
            // use main field instead
            $field = $fieldDefs['mainField'];
            $type = $this->getMetadata()->get(['entityDefs', $configuration['entity'], 'fields', $field, 'type']);
            $valueResult = $this->convertor->convertType($type, $record, array_merge($configuration, ['field' => $field]))[$column];
            $unitResult = $record[$field . 'UnitName'] ?? '';
        } else {
            $attribute = $this->convertor->getAttributeById($attributeId);
            $field = $configuration['field'];
            $type = $attribute->get('type');

            if (!empty($record[$field])){
                $valueParts = explode('::atro::', $record[$field]);

                $value = $valueParts[0] === 'N/A' ? null : (float)$valueParts[0];
                $unitId = !empty($valueParts[1]) && $valueParts[1] === 'N/A' ? null : $valueParts[1];
            }else{
                $value = null;
                $unitId = null;
            }

            $valueResult = $this->convertor->convertType($type, [$field => $value], array_merge($configuration, ['field' => $field]))[$column];
            $unitResult = $this->convertor->convertType('unit', ["{$field}Id" => $unitId], array_merge($configuration, ['field' => $field, 'exportBy' => ['name'], 'markForNoRelation' => '']))[$column];
        }

        if (in_array($type, ['rangeFloat', 'rangeInt'])) {
            $type = $type === 'rangeFloat' ? 'float' : 'int';
            $valueFromResult = $this->convertor->convertType($type, $record, array_merge($configuration, ['field' => $field . 'From']))[$column];
            $valueToResult = $this->convertor->convertType($type, $record, array_merge($configuration, ['field' => $field . 'To']))[$column];
            $result[$column] = "";

            if (!$this->isNullorEmptyResult($valueFromResult) && !$this->isNullorEmptyResult($valueToResult)) {
                $result[$column] = "$valueFromResult - $valueToResult";
            } else if (!$this->isNullorEmptyResult($valueFromResult)) {
                $result[$column] = ">= $valueFromResult";
            } else if (!$this->isNullorEmptyResult($valueToResult)) {
                $result[$column] = "<= $valueToResult";
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

    protected function prepareQueryCallbackForAttribute(QueryBuilder $qb, array $conf, string $alias): void
    {
        $attribute = $this->convertor->getAttributeById($conf['attributeId']);

        $qb->select("STRING_AGG(COALESCE($alias.{$attribute->get('type')}_value::text, 'N/A') || '::atro::' || COALESCE($alias.reference_value, 'N/A'), ', ')");
    }
}
