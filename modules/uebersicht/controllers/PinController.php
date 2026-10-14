<?php

namespace humhub\modules\uebersicht\controllers;

use humhub\components\access\ControllerAccess;
use humhub\components\Controller;
use humhub\modules\content\models\Content;
use humhub\modules\uebersicht\Module;
use yii\web\NotFoundHttpException;

class PinController extends Controller
{
    protected function getAccessRules()
    {
        return [[ControllerAccess::RULE_ADMIN_ONLY]];
    }

    /** Anpinnen bzw. lösen (POST, nur Systemadmins) */
    public function actionUmschalten($id)
    {
        $this->forcePostRequest();
        $content = Content::findOne(['id' => (int)$id]);
        if (!$content) {
            throw new NotFoundHttpException();
        }
        /** @var Module $module */
        $module = $this->module;
        if ($module->istAngepinnt($content->id)) {
            $module->loesen($content->id);
            return $this->asJson(['success' => true, 'angepinnt' => false]);
        }
        $module->anpinnen($content->id);
        return $this->asJson(['success' => true, 'angepinnt' => true]);
    }
}
