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
            $configuration['exportPav'] = true;

            return $this->convertType($this->prepareConvertorTypeForAttribute($type, 'value'), $record, array_merge($configuration, ['attributeId' => $record['attributeId']]));
        }

        return parent::convert($record, $configuration);
    }
}
