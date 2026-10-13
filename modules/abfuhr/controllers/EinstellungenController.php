<?php

namespace humhub\modules\abfuhr\controllers;

use humhub\components\access\ControllerAccess;
use humhub\modules\abfuhr\components\Erinnerung;
use humhub\modules\abfuhr\notifications\AbfuhrMorgen;
use humhub\modules\user\components\BaseAccountController;
use Yii;

/** Konto → Abfuhr-Erinnerung */
class EinstellungenController extends BaseAccountController
{
    protected function getAccessRules()
    {
        return [[ControllerAccess::RULE_LOGGED_IN_ONLY], [ControllerAccess::RULE_POST => ['test']]];
    }

    public function actionIndex()
    {
        $user = Yii::$app->user->getIdentity();
        if (Yii::$app->request->isPost) {
            $r = Yii::$app->request;
            Erinnerung::save($user, (bool)$r->post('aktiv'), (string)$r->post('gebiet'), (array)$r->post('tonnen', []), (string)$r->post('zeit'));
            $this->view->saved();
            return $this->redirect(['index']);
        }
        [$aktiv, $gebiet, $tonnen, $zeit] = Erinnerung::settings($user);
        $termine = Erinnerung::termine(date('Y-m-d'), date('Y-m-d', strtotime('+28 days')), $gebiet);
        return $this->render('index', [
            'aktiv' => $aktiv,
            'gebiet' => $gebiet,
            'tonnen' => $tonnen,
            'zeit' => $zeit,
            'termine' => $termine,
            'module' => $this->module,
        ]);
    }

    /** Probe: Erinnerung für die nächste Abfuhr sofort schicken (Glocke/Push wie eingestellt) */
    public function actionTest()
    {
        $user = Yii::$app->user->getIdentity();
        [, $gebiet, $tonnen] = Erinnerung::settings($user);
        foreach (Erinnerung::termine(date('Y-m-d'), date('Y-m-d', strtotime('+60 days')), $gebiet) as $tag => $keys) {
            $keys = array_values(array_intersect($keys, $tonnen ?: array_keys($this->module->tonnen)));
            if ($keys) {
                AbfuhrMorgen::instance()->payload(['tag' => $tag, 'tonnen' => $keys, 'heute' => $tag === date('Y-m-d')])->send($user);
                $this->view->success('Test-Erinnerung verschickt – sie erscheint gleich in der Glocke und, falls erlaubt, als Push.');
                return $this->redirect(['index']);
            }
        }
        $this->view->warn('Keine passende Abfuhr in den nächsten 60 Tagen gefunden.');
        return $this->redirect(['index']);
    }
}
