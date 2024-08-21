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

class VarcharType extends AbstractType
{
    public function getSelectColumn(array $configuration): string
    {
        $selectColumn = 'varchar_value';

        if (!empty($configuration['attributeValue']) && $configuration['attributeValue'] === 'id') {
            $selectColumn = 'id';
        }

        return $selectColumn;
    }

    public function convertToString(array &$result, array $record, array $configuration): void
    {
        $field = $configuration['field'];
        $column = $configuration['column'];
        $result[$column] = $configuration['nullValue'];

        if (array_key_exists($field, $record)) {
            $value = $record[$field];
            if (($value === null || $value === '') && !empty($configuration['fallbackField']) && array_key_exists($configuration['fallbackField'], $record)) {
                $value = $record[$configuration['fallbackField']];
            }

            if ($value === null || $value === '') {
                $result[$column] = $value === null ? $configuration['nullValue'] : $configuration['emptyValue'];
            } else {
                $result[$column] = $value;
            }
        } else {
            if ($field === 'sharedDownloadUrl') {
                $result[$column] = $this->getSharedDownloadUrl($this->getMemoryStorage()->get('exportJobId'), $record['id']);
            }
        }
    }
}
