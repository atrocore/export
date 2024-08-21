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

namespace Export\DataConvertor;

class ProductAttributeValueConvertor extends Convertor
{
    /**
     * @inheritDoc
     */
    public function convert(array $record, array $configuration): array
    {
        if ($configuration['field'] === 'value' && !empty($record['attributeId'])) {
            $attribute = $this->getAttributeById($record['attributeId']);
            $type = $attribute->get('type');
            if ($type === 'rangeFloat' || $type === "rangeInt") {
                $configuration['field'] = 'valueFrom';
            }

            return $this->convertType($this->getTypeForAttributeValue($type, 'value'), $record, $configuration);
        }

        return parent::convert($record, $configuration);
    }

    protected function getTypeForAttributeValue(string $attributeType, ?string $attributeValue): string
    {
        if ($attributeValue == null) {
            $attributeValue = 'value';
        }

        if ($attributeValue === 'id') {
            return 'varchar';
        }

        if ($attributeValue === 'value'
            && in_array($attributeType, ['int', 'float', 'rangeInt', 'rangeFloat', 'varchar'])) {
            return 'valueWithUnit';
        }

        if ($attributeValue === 'valueUnit') {
            return 'unit';
        }

        if ($attributeType === 'rangeInt') {
            return 'int';
        }

        if ($attributeType === 'rangeFloat') {
            return 'float';
        }

        return $attributeType;
    }
}
