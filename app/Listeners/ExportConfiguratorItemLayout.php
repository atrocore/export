<?php
/*
 * Copyright (c) AtroCore GmbH. All rights reserved. Proprietary software,
 * not free of charge, not open source.
 *
 * CONFIDENTIAL trade secret (GeschGehG, Directive (EU) 2016/943). Do not
 * disclose, publish or upload to third-party services.
 *
 * Use requires a valid license and is governed exclusively by the applicable
 * version of the AtroCore EULA: https://www.atrocore.com/en/eula
 * No modification, no redistribution. Retain this notice in all copies.
 */

declare(strict_types=1);

namespace Export\Listeners;

use Atro\Core\EventManager\Event;
use Atro\Listeners\AbstractLayoutListener;

class ExportConfiguratorItemLayout extends AbstractLayoutListener
{
    public function detail(Event $event): void
    {
        $result = $event->getArgument('result');

        $max = (int)($this->getMetadata()->get(['scopes', 'ExportFeed', 'maxNumberOfHeaders']) ?? 1);

        for ($k = 1; $k <= $max; $k++) {
            $result[] = [
                'label' => 'Header ' . $k,
                'name'  => 'header' . $k,
                'style' => 'default',
                'rows'  => [
                    [
                        ['name' => 'headerProperty' . $k, 'customLabel' => 'Column Label'],
                        ['name' => 'headerText' . $k, 'customLabel' => 'Column Name'],
                    ],
                ],
            ];
        }

        $event->setArgument('result', $result);
    }

    /**
     * headerText1..headerTextN (see Export\Listeners\Metadata::prepareHeaderFields()) are not part
     * of the static list.json - all of them, including headerText1, are added here so the
     * relationship panel (export:views/export-feed/record/panels/configurator-items) always shows
     * every configured header, whatever the feed's current numberOfHeaders is.
     */
    public function list(Event $event): void
    {
        $result = $event->getArgument('result');

        $max = (int)($this->getMetadata()->get(['scopes', 'ExportFeed', 'maxNumberOfHeaders']) ?? 1);

        for ($k = 1; $k <= $max; $k++) {
            $result[] = ['name' => 'headerText' . $k, 'customLabel' => $max === 1 ? 'Header' : 'Header ' . $k];
        }

        $event->setArgument('result', $result);
    }
}
