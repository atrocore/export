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

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Templates\Repositories\Base;
use Espo\Core\Utils\Json;
use Espo\ORM\Entity;
use Export\Entities\ExportFeed as ExportFeedEntity;

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
        if (!empty($exportFeed->get('data'))) {
            $data = json_decode(json_encode($exportFeed->get('data')), true);
            if (!empty($data['where']) && is_array($data['where'])) {
                foreach ($data['where'] as $k => $item) {
                    if (!empty($item['data']['unexported'])) {
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

    public function removeInvalidConfiguratorItems(string $exportFeedId): void
    {
        $exportFeed = $this->get($exportFeedId);
        if (empty($exportFeed)) {
            return;
        }

        $languages = ['', 'main'];
        if ($this->getConfig()->get('isMultilangActive', false)) {
            $languages = array_merge($languages, $this->getConfig()->get('inputLanguageList', []));
        }

        try {
            $this->getConnection()->createQueryBuilder()
                ->update('export_feed', 't')
                ->set($this->getConnection()->quoteIdentifier('language'), ':language')
                ->where('t.id = :id')
                ->andWhere('t.language NOT IN (:languages)')
                ->setParameter('language', '')
                ->setParameter('id', $exportFeed->get('id'))
                ->setParameter('languages', $languages, Connection::PARAM_STR_ARRAY)
                ->executeQuery();
        } catch (\Throwable $e) {
        }

        try {
            $this->getConnection()->createQueryBuilder()
                ->update('export_configurator_item', 't')
                ->set($this->getConnection()->quoteIdentifier('deleted'), ':true')
                ->where('t.language NOT IN (:languages)')
                ->setParameter('true', true, ParameterType::BOOLEAN)
                ->setParameter('languages', $languages, Connection::PARAM_STR_ARRAY)
                ->executeQuery();
        } catch (\Throwable $e) {
        }

        try {
            $this->getConnection()->createQueryBuilder()
                ->update('export_configurator_item', 't')
                ->set('channel_id', ':null')
                ->where('t.channel_id IS NOT NULL')
                ->andWhere("t.channel_id NOT IN (SELECT c.id FROM {$this->getConnection()->quoteIdentifier('channel')} c WHERE c.deleted=:false)")
                ->setParameter('null', null)
                ->setParameter('false', false, ParameterType::BOOLEAN)
                ->executeQuery();
        } catch (\Throwable $e) {
        }

        try {
            $this->getConnection()->createQueryBuilder()
                ->update('export_configurator_item', 't')
                ->set('deleted', ':true')
                ->where('t.export_feed_id = :id')
                ->andWhere('t.type = :type')
                ->andWhere('t.channel_id IS NOT NULL')
                ->andWhere("t.channel_id NOT IN (SELECT c.id FROM {$this->getConnection()->quoteIdentifier('channel')} c WHERE c.deleted=:false)")
                ->setParameter('true', true, ParameterType::BOOLEAN)
                ->setParameter('id', $exportFeed->get('id'))
                ->setParameter('type', 'Attribute')
                ->setParameter('false', false, ParameterType::BOOLEAN)
                ->executeQuery();
        } catch (\Throwable $e) {
        }

        try {
            $this->getConnection()->createQueryBuilder()
                ->update('export_configurator_item', 't')
                ->set('deleted', ':true')
                ->where('t.export_feed_id = :id')
                ->andWhere('t.type = :type')
                ->andWhere("t.attribute_id NOT IN (SELECT a.id FROM {$this->getConnection()->quoteIdentifier('attribute')} a WHERE a.deleted=:false)")
                ->setParameter('true', true, ParameterType::BOOLEAN)
                ->setParameter('id', $exportFeed->get('id'))
                ->setParameter('type', 'Attribute')
                ->setParameter('false', false, ParameterType::BOOLEAN)
                ->executeQuery();
        } catch (\Throwable $e) {
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

        parent::beforeSave($entity, $options);

        if (!$entity->isNew() && $entity->isAttributeChanged('language') && !empty($entity->get('language'))) {
            // Fix column type when global language is set on export Feed
            $qb = $this->getConnection()->createQueryBuilder();
            $qb->update('export_configurator_item')
                ->set('column_type', ':newColumnType')
                ->where('column_type = :columnType and export_feed_id= :exportFeedId')
                ->andWhere('deleted=:false')
                ->setParameters([
                    'newColumnType' => 'name',
                    'columnType'    => 'internal',
                    'exportFeedId'  => $entity->get('id')
                ])
                ->setParameter('false', false, ParameterType::BOOLEAN)
                ->executeQuery();
        }

        if ($entity->get('type') === 'simple') {
            $entity->set('convertCollectionToString', true);
            $entity->set('convertRelationsToString', true);

            // remove configurator items on Entity change
            if (!$entity->isNew() && $entity->has('entity') && $fetchedEntity !== $entity->get('entity')) {
                $this->removeConfiguratorItems('ExportFeed', $entity->get('id'));
            }
        }
    }

    protected function afterSave(Entity $entity, array $options = [])
    {
        parent::afterSave($entity, $options);

        if ($entity->isAttributeChanged('lastTime')) {
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

    protected function isDelimiterValid(Entity $entity): void
    {
        $delimiters = [
            (string)$entity->getFeedField('delimiter'),
            (string)$entity->getFeedField('decimalMark'),
            (string)$entity->getFeedField('thousandSeparator'),
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
