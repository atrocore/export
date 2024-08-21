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

class TextType extends VarcharType
{
    public function getAttributeSelectColumn(array $configuration): string
    {
        $selectColumn = parent::getAttributeSelectColumn($configuration);
        if (!empty($configuration['attributeValue']) && $configuration['attributeValue'] === 'value') {
            $selectColumn = 'text_value';
        }

        return $selectColumn;
    }
}
