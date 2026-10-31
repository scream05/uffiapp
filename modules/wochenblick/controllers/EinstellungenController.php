<?php

namespace humhub\modules\wochenblick\controllers;

use humhub\components\access\ControllerAccess;
use humhub\modules\user\components\BaseAccountController;
use humhub\modules\wochenblick\components\Ueberblick;
use Yii;
use yii\web\NotFoundHttpException;

/** Konto → Wochenüberblick (nur freigegebene Konten, Module::$freigabe) */
class EinstellungenController extends BaseAccountController
{
    protected function getAccessRules()
    {
        return [[ControllerAccess::RULE_LOGGED_IN_ONLY], [ControllerAccess::RULE_POST => ['test']]];
    }

    public function beforeAction($action)
    {
        if (!$this->module->istFreigegeben(Yii::$app->user->getIdentity())) {
            throw new NotFoundHttpException();
        }
        return parent::beforeAction($action);
    }

    public function actionIndex()
    {
        $user = Yii::$app->user->getIdentity();
        if (Yii::$app->request->isPost) {
            Ueberblick::save($user, (bool)Yii::$app->request->post('aktiv'), (bool)Yii::$app->request->post('mail'));
            $this->view->saved();
            return $this->redirect(['index']);
        }
        [$aktiv, $mail] = Ueberblick::settings($user);
        return $this->render('index', ['aktiv' => $aktiv, 'mail' => $mail, 'module' => $this->module, 'email' => (string)$user->email]);
    }

    /** Probe: Überblick sofort schicken (Push und, falls eingestellt, E-Mail) */
    public function actionTest()
    {
        $user = Yii::$app->user->getIdentity();
        Ueberblick::senden($user)
            ? $this->view->success(Yii::t('WochenblickModule.base', 'Überblick verschickt – er erscheint gleich in der Glocke, als Push und, falls eingestellt, im Postfach.'))
            : $this->view->warn(Yii::t('WochenblickModule.base', 'Die E-Mail konnte nicht verschickt werden.'));
        return $this->redirect(['index']);
    }
}
