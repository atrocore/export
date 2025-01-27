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

namespace Export\Migrations;

use Atro\Core\Migration\Base;
use Doctrine\DBAL\ParameterType;

class V1Dot8Dot66 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2025-01-16 18:00:00');
    }

    public function up(): void
    {
        $this->execute("ALTER TABLE export_feed ADD locale_id VARCHAR(36) DEFAULT NULL");

        $this->getConnection()->createQueryBuilder()
            ->update('export_configurator_item')
            ->set('column_type', ':nameType')
            ->where('column_type=:internalType')
            ->setParameter('nameType', 'name')
            ->setParameter('internalType', 'internal')
            ->executeQuery();

        try {
            $exportFeeds = $this->getConnection()->createQueryBuilder()
                ->select('*')
                ->from('export_feed')
                ->where('deleted=:false')
                ->setParameter('false', false, ParameterType::BOOLEAN)
                ->fetchAllAssociative();
        } catch (\Throwable $e) {
            $exportFeeds = [];
        }

        $newLocales = [];

        foreach ($exportFeeds as $exportFeed) {
            $data = @json_decode($exportFeed['data'], true);

            $language = $this->getConfig()->get('mainLanguage', 'en_US');
            if (!empty($exportFeed['language']) && $exportFeed['language'] !== $language) {
                $language = $exportFeed['language'];
            }
            $decimalMark = $data['feedFields']['decimalMark'] ?? '.';
            $thousandSeparator = $data['feedFields']['thousandSeparator'] ?? '';

            $id = substr(md5("{$language}_{$decimalMark}_$thousandSeparator"), 0, 36);

            $newLocales[$id] = [
                'id'                => $id,
                'languageCode'      => $language,
                'decimalMark'       => $decimalMark,
                'thousandSeparator' => $thousandSeparator
            ];

            $this->getConnection()->createQueryBuilder()
                ->update('export_feed')
                ->set('locale_id', ':localeId')
                ->where('id=:id')
                ->setParameter('localeId', $id)
                ->setParameter('id', $exportFeed['id'])
                ->executeQuery();
        }

        if (!empty($newLocales)) {
            if (!is_dir('data/reference-data')) {
                mkdir('data/reference-data');
            }

            if (file_exists('data/reference-data/Locale.json')) {
                $locales = @json_decode(@file_get_contents('data/reference-data/Locale.json'), true);
            }

            if (empty($locales)) {
                $locales = [];
            }

            $number = 1;
            foreach ($newLocales as $newLocale) {
                if (isset($locales[$newLocale['id']])) {
                    continue;
                }
                $locales[$newLocale['id']] = [
                    'id'                => $newLocale['id'],
                    'name'              => "Locale $number",
                    'code'              => $newLocale['id'],
                    'timeFormat'        => 'HH:mm',
                    'weekStart'         => 'sunday',
                    'dateFormat'        => 'MM\/DD\/YYYY',
                    'timeZone'          => 'UTC',
                    'createdAt'         => date('Y-m-d H:i:s'),
                    'modifiedAt'        => date('Y-m-d H:i:s'),
                    'createdById'       => 'system',
                    'languageCode'      => $newLocale['languageCode'],
                    'decimalMark'       => $newLocale['decimalMark'],
                    'thousandSeparator' => $newLocale['thousandSeparator']
                ];
                $number++;
            }

            file_put_contents('data/reference-data/Locale.json', json_encode($locales));
        }
    }

    protected function execute(string $query): void
    {
        try {
            $this->getPDO()->exec($query);
        } catch (\Throwable $e) {
        }
    }
}
