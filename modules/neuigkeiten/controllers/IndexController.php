<?php

namespace humhub\modules\neuigkeiten\controllers;

use humhub\components\Controller;
use humhub\modules\neuigkeiten\Module;
use Yii;

/**
 * Seite „Neuigkeiten“ (auch für Gäste) und Wegklicken des Popups
 *
 * @property Module $module
 */
class IndexController extends Controller
{
    protected function getAccessRules()
    {
        return [['login' => ['gesehen']], ['post' => ['gesehen']]];
    }

    public function actionIndex($art = '')
    {
        $art = isset(Module::ARTEN[$art]) ? $art : '';
        $this->view->setPageTitle(Yii::t('NeuigkeitenModule.base', 'Neuigkeiten'));
        return $this->render('index', [
            'module' => $this->module,
            'art' => $art,
            'eintraege' => array_values(array_filter($this->module->eintraege(),
                fn($e) => $e['datum'] <= date('Y-m-d') && ($art === '' || $e['art'] === $art))),
        ]);
    }

    /** Popup weggeklickt: nicht wieder zeigen */
    public function actionGesehen()
    {
        $this->module->alleGesehen(Yii::$app->user->getIdentity());
        return $this->asJson(['ok' => true]);
    }
}
