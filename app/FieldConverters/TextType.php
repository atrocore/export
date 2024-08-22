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

class TextType extends VarcharType
{
    protected function prepareQueryCallbackForAttribute(QueryBuilder $qb, array $conf, string $alias): void
    {
        $selectColumn = 'id';
        if (!empty($conf['attributeValue']) && $conf['attributeValue'] === 'value') {
            $selectColumn = 'text_value';
        }

        $qb->select("$alias.$selectColumn");
    }
}
