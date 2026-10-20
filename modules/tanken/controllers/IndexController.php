<?php

namespace humhub\modules\tanken\controllers;

use humhub\modules\content\components\ContentContainerController;
use humhub\modules\space\models\Space;
use humhub\modules\tanken\components\Spritpreise;
use Yii;
use yii\web\NotFoundHttpException;

/**
 * Läuft im Space „Gewerbe & Einkaufen“ (/s/gewerbe/tanken/index), damit Space-Kopf und -Menü sichtbar bleiben.
 * /tanken/index wird dorthin umgeleitet. Auch für Gäste.
 */
class IndexController extends ContentContainerController
{
    public $requireContainer = false;

    protected function getAccessRules()
    {
        return [];
    }

    public function beforeAction($action)
    {
        if (!parent::beforeAction($action)) {
            return false;
        }
        if (!$this->module->aktiv() || ($this->contentContainer !== null && $this->contentContainer->url !== $this->module->spaceUrl)) {
            throw new NotFoundHttpException();
        }
        if ($this->contentContainer === null && ($space = Space::findOne(['url' => $this->module->spaceUrl])) !== null) {
            $this->redirect($space->createUrl('/tanken/index', Yii::$app->request->get()));
            return false;
        }
        return true;
    }

    public function actionIndex($sorte = 'e5', $umkreis = null, $nach = 'preis')
    {
        $m = $this->module;
        $sorte = isset(Spritpreise::SORTEN[$sorte]) ? $sorte : 'e5';
        $umkreis = in_array((int)$umkreis, $m->umkreise, true) ? (int)$umkreis : $m->kastenRadius;
        $daten = Spritpreise::daten();
        $this->view->setPageTitle(Yii::t('TankenModule.base', 'Spritpreise'));
        return $this->render('index', [
            'module' => $m,
            'daten' => $daten,
            'sorte' => $sorte,
            'umkreis' => $umkreis,
            'nachPreis' => $nach !== 'km',
            'liste' => $daten ? Spritpreise::liste($daten, $umkreis, $sorte, $nach !== 'km') : [],
            'verlauf' => Spritpreise::tagesverlauf($sorte),
        ]);
    }
}
