<?php

namespace humhub\modules\notdienst\controllers;

use humhub\components\access\ControllerAccess;
use humhub\modules\content\components\ContentContainerController;
use humhub\modules\notdienst\components\Notdienst;
use humhub\modules\space\models\Space;
use Yii;
use yii\web\NotFoundHttpException;

/**
 * Läuft im Space „Rathaus Uffenheim“ (/s/rathaus-uffenheim/notdienst/index), damit Space-Kopf und -Menü
 * sichtbar bleiben. Die alte Adresse /notdienst/index wird dorthin umgeleitet.
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
            $this->redirect($space->createUrl('/notdienst/index', Yii::$app->request->get()));
            return false;
        }
        return true;
    }

    public function actionIndex()
    {
        return $this->render('index', [
            'module' => $this->module,
            'periods' => Notdienst::periods(3),
            'stale' => Notdienst::isStale(),
            'data' => Notdienst::load(),
        ]);
    }
}
