<?php

namespace humhub\modules\wochenblick\controllers;

use humhub\components\access\ControllerAccess;
use humhub\components\Controller;
use humhub\modules\wochenblick\components\Ueberblick;
use Yii;
use yii\web\NotFoundHttpException;

/** Der Überblick als Seite (Ziel von Push und Glocke): /wochenblick/ansicht – nur freigegebene Konten */
class AnsichtController extends Controller
{
    protected function getAccessRules()
    {
        return [[ControllerAccess::RULE_LOGGED_IN_ONLY]];
    }

    public function actionIndex()
    {
        $user = Yii::$app->user->getIdentity();
        if (!$this->module->istFreigegeben($user)) {
            throw new NotFoundHttpException();
        }
        return $this->render('index', ['d' => Ueberblick::daten($user)]);
    }
}
