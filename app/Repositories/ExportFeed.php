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

namespace Export\Repositories;

use Atro\Core\Utils\Util;
use Doctrine\DBAL\ParameterType;
use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Templates\Repositories\Base;
use Espo\Core\Utils\Json;
use Espo\ORM\Entity;
use Export\Entities\ExportFeed as ExportFeedEntity;
use Export\Services\AbstractExportType;

class ExportFeed extends Base
{
    public function updateLastTime(string $exportFeedId, \DateTime $lastTime): void
    {
        $qb = $this->getConnection()->createQueryBuilder()
            ->update('export_feed')
            ->set('last_time', ':lastTime')
            ->where('id=:id')
            ->setParameter('lastTime', $lastTime->format('Y-m-d H:i:s'))
            ->setParameter('id', $exportFeedId);

        $exportFeed = $this->get($exportFeedId);
        if (!empty($exportFeed) && !empty($exportFeed->get('data'))) {
            $data = json_decode(json_encode($exportFeed->get('data')), true);
            if (!empty($data['where']) && is_array($data['where'])) {
                foreach ($data['where'] as $k => $item) {
                    if(!empty($item['value']) && is_array($item['value']) && in_array('unexported', $item['value'])) {
                        $data['where'][$k]['data']['unexported'] = $lastTime->format('Y-m-d H:i:s');
                        $qb->set('data', ':data')->setParameter('data', json_encode($data));
                        break;
                    }
                }
            }
        }

        $qb->executeQuery();
    }

    public function updateLastStatus(string $exportFeedId, string $lastStatus): void
    {
        $this->getConnection()->createQueryBuilder()
            ->update('export_feed')
            ->set('last_status', ':lastStatus')
            ->where('id=:id')
            ->setParameter('lastStatus', $lastStatus)
            ->setParameter('id', $exportFeedId)
            ->executeQuery();
    }

    public function hasDeletedRecordsToClear(): bool
    {
        Util::removeDir(AbstractExportType::TMP_DIR);

        return parent::hasDeletedRecordsToClear();
    }

    public function fixLocaleIfNecessary(string $exportFeedId): void
    {
        $exportFeed = $this->get($exportFeedId);
        if (empty($exportFeed)) {
            return;
        }

        if (empty($exportFeed->get('localeId'))
            || empty($this->getEntityManager()->getRepository('Locale')->get($exportFeed->get('localeId')))
        ) {
            $mainLocaleId = null;
            $locales = array_values($this->getConfig()->get('referenceData.Locale'));
            if (!empty($locales)) {
                foreach ($locales as $locale) {
                    if ($locale['id'] === 'main') {
                        $mainLocaleId = $locale['id'];
                        break;
                    }
                }
                if (empty($mainLocaleId)) {
                    $mainLocaleId = $locales[0]['id'];
                }
            }

            if (!empty($mainLocaleId)) {
                try {
                    $this->getConnection()->createQueryBuilder()
                        ->update('export_feed')
                        ->set('locale_id', ':mainLocaleId')
                        ->where('id = :id')
                        ->setParameter('mainLocaleId', $mainLocaleId)
                        ->setParameter('id', $exportFeed->get('id'))
                        ->executeQuery();
                } catch (\Throwable $e) {
                }
            }
        }
    }

    public function getIdsByExportEntity(string $exportEntity): array
    {
        $feeds = $this
            ->select(['id'])
            ->where(['data*' => '%\"entity\":\"' . $exportEntity . '\"%'])
            ->find();

        return array_column($feeds->toArray(), 'id');
    }

    public function removeConfiguratorItems(string $entityType, string $id): void
    {
        try {
            $this
                ->getDbal()
                ->createQueryBuilder()
                ->delete('export_configurator_item')
                ->where('export_feed_id = :exportFeedId')
                ->andWhere('deleted = :true')
                ->setParameter('exportFeedId', $id)
                ->setParameter('true', true, ParameterType::BOOLEAN)
                ->executeQuery();
        } catch (\Throwable $e) {

        }

        $this->getEntityManager()->getRepository('ExportConfiguratorItem')->where([lcfirst($entityType) . 'Id' => $id])->removeCollection();
    }

    public function countRelated(Entity $entity, $relationName, array $params = []): int
    {
        if ($relationName == 'exportJobs') {
            $connection = $this->getConnection();
            $qb = $connection->createQueryBuilder();

            $res = $qb
                ->select('COUNT(id) AS count')
                ->from($connection->quoteIdentifier('export_job'))
                ->where('export_feed_id = :export_feed_id')
                ->andWhere('deleted = :false')
                ->setParameter('export_feed_id', $entity->id)
                ->setParameter('false', false, ParameterType::BOOLEAN)
                ->fetchAssociative();

            return (int)($res['count'] ?? 0);
        }

        return parent::countRelated($entity, $relationName, $params);
    }

    protected function beforeSave(Entity $entity, array $options = [])
    {
        $fetchedEntity = $entity->getFeedField('entity');

        $this->setFeedFieldsToDataJson($entity);

        if (empty($options['skipAll'])) {
            $this->isDelimiterValid($entity);
        }

        if ($entity->isNew()) {
            $entity->set('lastStatus', null);
            $entity->set('lastTime', null);
        }

        $fileTypes = $this->getMetadata()->get("app.exportTypes.{$entity->get('type')}.fileTypes") ?? [];

        if (
            $entity->isAttributeChanged('fileType')
            && !empty($entity->get('fileType'))
            && !in_array($entity->get('fileType'), $fileTypes)
        ) {
            throw new BadRequest("Wrong file Format has been chosen.");
        }

        if (empty($entity->get('fileType'))) {
            $entity->set('separateJob', false);
            $entity->set('limit', 1);
        }

        parent::beforeSave($entity, $options);

        if ($entity->get('type') === 'simple') {
            $entity->set('convertCollectionToString', true);
            $entity->set('convertRelationsToString', true);

            // remove configurator items on Entity change
            if (!$entity->isNew() && $entity->has('entity') && $fetchedEntity !== $entity->get('entity')) {
                $this->removeConfiguratorItems('ExportFeed', $entity->get('id'));
            }
        }

        if ($entity->get('code') === '') {
            $entity->set('code', null);
        }

        if (!empty($entity->get('separateJob'))) {
            $entity->set('replaceExistingFile', false);
        }
    }

    public function save(Entity $entity, array $options = [])
    {
        if ($entity->isNew() && !empty($entity->_shouldNotSave)) {
            return false;
        }
        return parent::save($entity, $options);
    }

    protected function afterSave(Entity $entity, array $options = [])
    {
        parent::afterSave($entity, $options);

        if ($entity->isAttributeChanged('lastTime') && !empty($entity->get('lastTime'))) {
            $this->updateLastTime($entity->get('id'), new \DateTime($entity->get('lastTime')));
        }
    }

    protected function beforeRemove(Entity $entity, array $options = [])
    {
        parent::beforeRemove($entity, $options);

        $this->removeConfiguratorItems('ExportFeed', $entity->get('id'));

        $shares = $this->getEntityManager()->getRepository('Sharing')
            ->where(['exportFeedId' => $entity->get('id')])
            ->find();
        foreach ($shares as $share) {
            $this->getEntityManager()->removeEntity($share);
        }
    }

    protected function afterRestore($entity)
    {
        parent::afterRestore($entity);

        try {
            $this
                ->getDbal()
                ->createQueryBuilder()
                ->update('export_configurator_item')
                ->set('deleted', ':false')
                ->where('export_feed_id = :exportFeedId')
                ->andWhere('deleted = :true')
                ->setParameter('exportFeedId', $entity->get('id'))
                ->setParameter('false', false, ParameterType::BOOLEAN)
                ->setParameter('true', true, ParameterType::BOOLEAN)
                ->executeQuery();
        } catch (\Throwable $e) {
            $GLOBALS['log']->error('ExportFeed restore error: ' . $e->getMessage());
        }
    }

    protected function init()
    {
        parent::init();

        $this->addDependency('language');
    }

    protected function setFeedFieldsToDataJson(Entity $entity): void
    {
        $data = !empty($data = $entity->get('data')) ? Json::decode(Json::encode($data), true) : [];

        foreach ($this->getMetadata()->get(['entityDefs', 'ExportFeed', 'fields'], []) as $field => $row) {
            if (empty($row['notStorable']) || empty($row['dataField'])) {
                continue 1;
            }
            if ($entity->has($field)) {
                $data[ExportFeedEntity::DATA_FIELD][$field] = $entity->get($field);
            }
        }

        if (isset($data['configuration'])) {
            unset($data['configuration']);
        }

        $entity->set('data', Json::decode(Json::encode($data)));
    }

    protected function isDelimiterValid(ExportFeedEntity $entity): void
    {
        $delimiters = [
            (string)$entity->getFeedField('delimiter'),
            $entity->getDecimalMark(),
            $entity->getThousandSeparator(),
            (string)$entity->getFeedField('fieldDelimiterForRelation'),
        ];

        if ($entity->get('fileType') == 'csv') {
            $delimiters[] = (string)$entity->getFeedField('csvFieldDelimiter');
        }

        if (count(array_unique($delimiters)) !== count($delimiters)) {
            throw new BadRequest($this->getInjection('language')->translate('delimitersMustBeDifferent', 'messages', 'ExportFeed'));
        }
    }
}
