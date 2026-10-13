<?php

namespace humhub\modules\designwahl\controllers;

use humhub\components\access\ControllerAccess;
use humhub\components\Controller;
use humhub\modules\designwahl\Module;
use Yii;

/**
 * @property Module $module
 */
class WechselController extends Controller
{
    protected function getAccessRules()
    {
        return [
            [ControllerAccess::RULE_LOGGED_IN_ONLY],
            [ControllerAccess::RULE_POST => ['index']],
        ];
    }

    public function actionIndex($design)
    {
        $this->module->setDesign((string)$design);
        return $this->redirect(Yii::$app->request->referrer ?: ['/dashboard/dashboard']);
    }
}
