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

namespace Export\TwigFunction;

use Pim\Entities\Category;

class PreviousCategoryId extends AbstractTwigFunction
{
    public function run($category)
    {
        if (empty($category) || !($category instanceof Category)) {
            return null;
        }

        $where = !empty($this->getFeedData()['data']['where']) ? $this->getFeedData()['data']['where'] : [];

        if (empty($category->get('categoryParentId'))) {
            $where[] = [
                'type'      => 'isNull',
                'attribute' => 'categoryParentId'
            ];
        } else {
            $where[] = [
                'type'      => 'equals',
                'attribute' => 'categoryParentId',
                'value'     => $category->get('categoryParentId')
            ];
        }

        $categories = $this
            ->container->get('serviceFactory')
            ->create('Category')
            ->findEntities(['where' => $where, 'sortBy' => 'sortOrder', 'asc' => true]);

        $before = null;
        foreach ($categories['collection'] as $cat) {
            if ($cat->get('id') === $category->get('id')) {
                return $before;
            }
            $before = $cat->get('id');
        }

        return null;
    }
}
