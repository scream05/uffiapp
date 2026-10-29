<?php

namespace humhub\modules\neuigkeiten\controllers;

use humhub\components\Controller;
use humhub\modules\neuigkeiten\Module;
use Yii;
use yii\web\NotFoundHttpException;

/**
 * Hilfe-Seiten für alle (auch Gäste): /hilfe/app (App-Anleitung), /hilfe/regeln (Community-Regeln) und /hilfe/barrierefreiheit (Erklärung zur Barrierefreiheit).
 * Inhalt: hilfe/<sprache>/<seite>.html, siehe Module::hilfeHtml()
 *
 * @property Module $module
 */
class HilfeController extends Controller
{
    public function actionIndex()
    {
        return $this->redirect(['/neuigkeiten/hilfe/seite', 'seite' => 'app']);
    }

    public function actionSeite($seite)
    {
        if (!isset(Module::HILFE[$seite])) {
            throw new NotFoundHttpException();
        }
        $this->view->setPageTitle(Module::hilfeTitel($seite));
        return $this->render('seite', ['seite' => $seite, 'html' => $this->module->hilfeHtml($seite)]);
    }
}
