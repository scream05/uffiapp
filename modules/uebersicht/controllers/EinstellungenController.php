<?php

namespace humhub\modules\uebersicht\controllers;

use humhub\components\Controller;
use humhub\modules\uebersicht\Module;
use Yii;

/** „Meine Übersicht“: Bereiche für die eigene Übersicht an- und ausschalten */
class EinstellungenController extends Controller
{
    protected function getAccessRules()
    {
        return [['login']];
    }

    public function actionIndex()
    {
        /** @var Module $module */
        $module = $this->module;
        $user = Yii::$app->user->getIdentity();
        $bereiche = $module->getBereiche($user);
        if (Yii::$app->request->isPost) {
            $an = (array)Yii::$app->request->post('an', []);
            $aus = [];
            foreach ($bereiche as $space) {
                if (!in_array($space->guid, $an, true)) {
                    $aus[] = $space->guid;
                }
            }
            $module->setAusgeblendet($user, $aus);
            $this->view->saved();
            return $this->redirect(['/dashboard/dashboard']);
        }
        return $this->render('index', ['bereiche' => $bereiche, 'aus' => $module->getAusgeblendet($user)]);
    }
}
