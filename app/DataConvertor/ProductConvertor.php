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

use Espo\Core\EventManager\Event;
use Espo\ORM\Entity;
use Espo\ORM\EntityCollection;

class ProductConvertor extends Convertor
{
    public function convert(array $record, array $configuration): array
    {
        if (isset($configuration['attributeId'])) {
            return $this->convertAttributeValue($record, $configuration);
        }

        return parent::convert($record, $configuration);
    }

    protected function convertAttributeValue(array $record, array $configuration): array
    {
        $result = [];

        $result[$configuration['column']] = $configuration['markForNoRelation'];

        $pavCollectionKeys = $this->getMemoryStorage()->get('pavCollectionKeys');

        /**
         * Exit if empty
         */
        if (empty($pavCollectionKeys)) {
            return $result;
        }

        $language = $configuration['language'];
        if (!empty($GLOBALS['languagePrism'])) {
            $language = $GLOBALS['languagePrism'];
        }

        $productAttribute = $this->searchAttributeValue($pavCollectionKeys, $record, $configuration, $language);

        if(
            (
                empty($productAttribute)
                || $productAttribute->get('value') === null
                || $productAttribute->get('value') === ""
            )
            && !empty($configuration['fallbackLanguage'])
        ) {
            $productAttribute = $this->searchAttributeValue(
                $pavCollectionKeys,
                $record,
                $configuration,
                $configuration['fallbackLanguage']
            );
        }

        if (!empty($productAttribute)) {

            $type = $this->getTypeForAttribute($productAttribute->get('attributeType'), $configuration['attributeValue']);
            $result = $this->convertType($type, $productAttribute->toArray(), array_merge($configuration, ['field' => $this->getFieldForAttribute($configuration)]));

        }

        $eventPayload = [
            'result'           => $result,
            'productAttribute' => $productAttribute,
            'record'           => $record,
            'configuration'    => $configuration
        ];

        return $this->getEventManager()->dispatch('ProductConvertor', 'convertAttributeValue', new Event($eventPayload))->getArgument('result');
    }

    /**
     * @param array $record
     * @param array $configuration
     * @param string|null $language
     *
     * @return mixed|null
     */
    protected function searchAttributeValue($pavCollectionKeys, array $record, array $configuration, ?string $language = '')
    {
        $result = null;

        // find Channel
        if (!empty($configuration['channelId'])) {
            $key = implode('_', [$record['id'], $configuration['attributeId'], $language, $configuration['channelId']]);
            if (isset($pavCollectionKeys[$key])) {
                $result = $this->getMemoryStorage()->get($pavCollectionKeys[$key]);
            }
        }


        if(empty($configuration['channelId']) || (empty($result) && !empty($configuration['replaceAttributeValues']))){
            // find Global
            $key = implode('_', [$record['id'], $configuration['attributeId'], $language, '']);
            if (isset($pavCollectionKeys[$key])) {
                $result = $this->getMemoryStorage()->get($pavCollectionKeys[$key]);
            }
        }

        if (empty($result) && !in_array($language, ['', 'main'])) {
            $result = $this->searchAttributeValue($pavCollectionKeys, $record, $configuration, 'main');
        }

        return $result;
    }

    public function getFieldForAttribute($configuration)
    {
        if (in_array($configuration['attributeValue'], ['valueNumeric', 'valueString'])) {
            return 'value';
        }
        return $configuration['attributeValue'] ?? 'value';
    }
}
