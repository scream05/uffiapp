<?php

namespace humhub\modules\oeffnungszeiten\controllers;

use humhub\modules\content\components\ContentContainerController;
use humhub\modules\oeffnungszeiten\components\Zeiten;
use humhub\modules\space\models\Space;
use Yii;
use yii\web\NotFoundHttpException;

/**
 * Läuft im Space „Rathaus Uffenheim“ (/s/rathaus-uffenheim/oeffnungszeiten/index), damit Space-Kopf und -Menü
 * sichtbar bleiben. /oeffnungszeiten/index wird dorthin umgeleitet.
 */
class IndexController extends ContentContainerController
{
    public $requireContainer = false;

    protected function getAccessRules()
    {
        return []; // auch für Gäste
    }

    public function beforeAction($action)
    {
        if (!parent::beforeAction($action)) {
            return false;
        }
        if ($this->contentContainer !== null && $this->contentContainer->url !== $this->module->spaceUrl) {
            throw new NotFoundHttpException();
        }
        if ($this->contentContainer === null && ($space = Space::findOne(['url' => $this->module->spaceUrl])) !== null) {
            $this->redirect($space->createUrl('/oeffnungszeiten/index', Yii::$app->request->get()));
            return false;
        }
        return true;
    }

    public function actionIndex()
    {
        $this->view->setPageTitle(Yii::t('OeffnungszeitenModule.base', 'Öffnungszeiten'));
        return Zeiten::ortszeit(fn() => $this->render('index', ['orte' => $this->module->orte()]));
    }
}
