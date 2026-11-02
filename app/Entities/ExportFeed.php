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

namespace Export\Entities;

use Atro\Core\Templates\Entities\Base;
use Espo\ORM\Entity;

class ExportFeed extends Base
{
    protected $entityType = "ExportFeed";

    public function getLocale(): ?Entity
    {
        return $this->getEntityManager()->getEntity('Locale', $this->get('localeId'));
    }

    public function getDecimalMark(): string
    {
        return $this->getLocale()->get('decimalMark') ?? '.';
    }

    public function getThousandSeparator(): string
    {
        return $this->getLocale()->get('thousandSeparator') ?? '';
    }
}
