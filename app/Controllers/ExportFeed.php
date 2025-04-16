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

namespace Export\Controllers;

use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Templates\Controllers\Base;
use Atro\Core\Exceptions;
use Espo\Core\Utils\Json;
use Slim\Http\Request;

class ExportFeed extends Base
{
    public function actionAddFields($params, $data, Request $request): bool
    {
        if (!$request->isPost() || !property_exists($data, 'id') || !property_exists($data, 'fields') || !property_exists($data, 'entityName')) {
            throw new Exceptions\BadRequest();
        }

        if (!$this->getAcl()->check($this->name, 'edit')) {
            throw new Exceptions\Forbidden();
        }

        return $this->getRecordService()->addFields($data->entityName, $data->id, $data->fields);
    }

    public function actionAddAttributes($params, $data, Request $request): bool
    {
        if (!$request->isPost() || !property_exists($data, 'id') || !property_exists($data, 'attributesIds') || !property_exists($data, 'entityName')) {
            throw new Exceptions\BadRequest();
        }

        if (!$this->getAcl()->check($this->name, 'edit')) {
            throw new Exceptions\Forbidden();
        }

        echo '<pre>';
        print_r('123');
        die();

        return $this->getRecordService()->addAttributes($data->entityName, $data->id, $data->attributesIds);
    }

    public function actionAddFixed($params, $data, Request $request): bool
    {
        if (!$request->isPost() || !property_exists($data, 'id')) {
            throw new Exceptions\BadRequest();
        }

        if (!$this->getAcl()->check($this->name, 'edit')) {
            throw new Exceptions\Forbidden();
        }

        return $this->getRecordService()->addFixed($data->id);
    }

    public function actionAddScript($params, $data, Request $request): bool
    {
        if (!$request->isPost() || !property_exists($data, 'id')) {
            throw new Exceptions\BadRequest();
        }

        if (!$this->getAcl()->check($this->name, 'edit')) {
            throw new Exceptions\Forbidden();
        }

        return $this->getRecordService()->addScript($data->id);
    }

    public function actionRemoveAllItems($params, $data, Request $request): bool
    {
        if (!$request->isPost() || !property_exists($data, 'entityType') || !property_exists($data, 'id')) {
            throw new Exceptions\BadRequest();
        }

        if (!$this->getAcl()->check($this->name, 'edit')) {
            throw new Exceptions\Forbidden();
        }

        return $this->getRecordService()->removeAllItems((string)$data->entityType, (string)$data->id);
    }

    public function actionExportFile($params, $data, Request $request): bool
    {
        if (!$request->isPost() || !property_exists($data, 'id')) {
            throw new Exceptions\BadRequest();
        }

        if (!$this->getAcl()->check($this->name, 'read')) {
            throw new Exceptions\Forbidden();
        }

        if (!$this->getAcl()->check('ExportJob', 'create')) {
            throw new Exceptions\Forbidden();
        }

        return $this->getRecordService()->exportFile($data);
    }

    public function actionExportChannel($params, $data, Request $request): bool
    {
        if (!$request->isPost() || !property_exists($data, 'id')) {
            throw new Exceptions\BadRequest();
        }

        if (!$this->getAcl()->check($this->name, 'read') || !$this->getAcl()->check('Channel', 'read')) {
            throw new Exceptions\Forbidden();
        }

        return $this->getRecordService()->exportChannel($data->id);
    }

    /**
     * @inheritDoc
     */
    protected function fetchListParamsFromRequest(&$params, $request, $data)
    {
        parent::fetchListParamsFromRequest($params, $request, $data);

        if ($request->get('exportEntity')) {
            $params['exportEntity'] = $request->get('exportEntity');
        }
    }

    public function actionEasyCatalogVerifyCode($params, $data, $request)
    {
        if (!$request->isGet() || empty($request->get("code"))) {
            throw new BadRequest();
        }
        return $this->getRecordService()->verifyCodeEasyCatalog($request->get("code"));
    }

    public function actionEasyCatalog($params, $data, Request $request)
    {
        if (!$request->isGet() || empty($request->get("code"))) {
            throw new Exceptions\BadRequest();
        }

        if (!$this->getAcl()->check($this->name, 'read')) {
            throw new Exceptions\Forbidden();
        }

        return $this->getRecordService()->getEasyCatalog($request->get("code"), $request->get('offset'));
    }

    public function actionLoadAvailableTemplates($params, $data, Request $request)
    {
        if (!$request->isPost() || empty($data)) {
            throw new Exceptions\BadRequest();
        }

        if (!$this->getAcl()->check($this->name, 'read')) {
            throw new Exceptions\Forbidden();
        }

        $data = Json::decode(Json::encode($data), true);

        return $this->getRecordService()->getAvailableTemplates($data);
    }

    public function actionGetOriginTemplate($params, $data, Request $request)
    {
        if (!$request->isGet() || empty($request->get("template"))) {
            throw new Exceptions\BadRequest();
        }

        if (!$this->getAcl()->check($this->name, 'read')) {
            throw new Exceptions\Forbidden();
        }

        return json_encode(['template' => $this->getRecordService()->getOriginTemplate($request->get("template"))]);
    }

    public function actionDirectExportFile($params, $data, Request $request): bool
    {
        if (!$request->isPost() || !property_exists($data, 'fileType') || !property_exists($data, 'scope')) {
            throw new Exceptions\BadRequest();
        }

        if (empty($data->exportAllField) && empty($data->fieldList)) {
            throw new Exceptions\BadRequest();
        }

        return  $this->getRecordService()->directExportFile($data);
    }
}
