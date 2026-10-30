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

namespace Export\ActionTypes;

use Atro\ActionTypes\AbstractAction;
use Atro\Core\EventManager\Event;
use Atro\DTOs\ActionResultDTO;
use Atro\Entities\ActionExecution;
use Espo\ORM\Entity;

class Export extends AbstractAction
{
    public function useMassActions(Entity $action, \stdClass $input): bool
    {
        return false;
    }

    public function execute(ActionExecution $execution, \stdClass $input): bool
    {
        return $this->executeWithResult($execution, $input)->toBool();
    }

    /**
     * @deprecated Use executeWithResult(). Kept for backward compatibility.
     * @todo 3.0.0: remove, executeWithResult() becomes execute().
     */
    public function executeNow(Entity $action, \stdClass $input): bool
    {
        return $this->execute($this->createFakeExecution($action), $input);
    }

    public function executeWithResult(ActionExecution $execution, \stdClass $input): ActionResultDTO
    {
        $action = $execution->get('action');

        if (empty($action->getSubtype()?->get('exportFeedId'))) {
            return ActionResultDTO::skipped('The export feed is not selected.');
        }

        $payload = empty($action->getSubtype()?->get('payload')) ? '' : (string)$action->getSubtype()?->get('payload');
        $templateData = [];

        if (!empty($action->get('sourceEntity'))) {
            $service = $this->getServiceFactory()->create($action->get('sourceEntity'));
            if (property_exists($input, 'entityId')) {
                $params = [
                    'disableCount' => true,
                    'where'        => [['type' => 'in', 'attribute' => 'id', 'value' => [$input->entityId]]],
                    'offset'       => 0,
                    'maxSize'      => 1,
                    'sortBy'       => 'createdAt',
                    'asc'          => true,
                    'withDeleted'  => true
                ];
            }

            if (property_exists($input, 'where')) {
                $params = [
                    'disableCount' => true,
                    'where'        => json_decode(json_encode($input->where), true),
                    'offset'       => 0,
                    'maxSize'      => 60000,
                    'sortBy'       => 'createdAt',
                    'asc'          => true
                ];
            }

            if (!empty($params)) {
                $res = $service->findEntities($params);
                if (!empty($res['collection'][0])) {
                    $templateData['sourceEntities'] = $res['collection'];
                    $templateData['sourceEntitiesIds'] = array_column($res['collection']->toArray(), 'id');

                    $templateData['entity'] = $res['collection'][0]; // for backward compatibility
                } else {
                    return ActionResultDTO::skipped('There are no records to export.');
                }
            }
        }

        $payload = $this->getTwig()->renderTemplate($payload, $templateData);
        $payload = @json_decode((string)$payload, true);
        if (!is_array($payload)) {
            $payload = [];
        }

        if (!empty($input->_relationData)) {
            $payload['relation'] = [
                'action'       => $input->_relationData['action'],
                'relationName' => $input->_relationData['relationName'],
                'foreignId'    => $input->_relationData['foreignId']
            ];
        }

        if (!empty($action->getSubtype()?->get('contentLanguageId'))) {
            $payload['contentLanguageId'] = $action->getSubtype()?->get('contentLanguageId');
        }

        if (!empty($action->getSubtype()?->get('localeId'))) {
            $payload['localeId'] = $action->getSubtype()?->get('localeId');
        }

        /** @var \Export\Services\ExportFeed $service */
        $service = $this->getServiceFactory()->create('ExportFeed');

        $exportFeed = $service->getEntity($action->getSubtype()?->get('exportFeedId'));
        if (empty($exportFeed) || empty($exportFeed->get('isActive'))) {
            return ActionResultDTO::skipped('The export feed is not active.');
        }

        $payload['executeNow'] = empty($action->get('inBackground'));
        if (property_exists($input, 'actionSetLinkerId')) {
            $payload['actionSetLinkerId'] = $input->actionSetLinkerId;

            // lets the action set continue with the original selection once the export jobs are finished
            if (!empty($input->actionSetInitialInput)) {
                $payload['actionSetInitialInput'] = $input->actionSetInitialInput;
            }

            // the filter defined in the payload of the action wins, the selection of the set is only the default
            if (empty($payload['where'])) {
                if (property_exists($input, 'where')) {
                    $payload['where'] = json_decode(json_encode($input->where), true);
                } elseif (property_exists($input, 'entityId')) {
                    $payload['where'] = [['type' => 'in', 'attribute' => 'id', 'value' => [$input->entityId]]];
                }
            }
        }

        $service->runExport($exportFeed->get('id'), json_encode($payload));

        if (empty($payload['executeNow'])) {
            // a job that creates the export jobs is waiting in the queue
            $this->markExecutionDone($execution);

            return ActionResultDTO::queued();
        }

        return ActionResultDTO::done();
    }
}