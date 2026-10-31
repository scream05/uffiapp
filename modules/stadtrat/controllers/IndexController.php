<?php

namespace humhub\modules\stadtrat\controllers;

use humhub\modules\content\components\ContentContainerController;
use humhub\modules\space\models\Space;
use humhub\modules\stadtrat\components\Ris;
use humhub\modules\stadtrat\models\Sitzung;
use Yii;
use yii\web\NotFoundHttpException;

/**
 * Läuft im Space „Rathaus Uffenheim“ (/s/rathaus-uffenheim/stadtrat/index), damit Space-Kopf und -Menü sichtbar bleiben.
 * /stadtrat wird dorthin umgeleitet. Nur für freigegebene Konten (Module::$freigabe; null = alle, auch Gäste).
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
        if (!$this->module->istFreigegeben(Yii::$app->user->getIdentity())
            || ($this->contentContainer !== null && $this->contentContainer->url !== $this->module->spaceUrl)) {
            throw new NotFoundHttpException();
        }
        if ($this->contentContainer === null && ($space = Space::findOne(['url' => $this->module->spaceUrl])) !== null) {
            $this->redirect($space->createUrl('/stadtrat/' . $action->id, Yii::$app->request->get()));
            return false;
        }
        return true;
    }

    public function actionIndex($gremium = '', $q = '', $alle = 0)
    {
        $filter = function ($query) use ($gremium, $q) {
            if ($gremium !== '') {
                $query->andWhere(['gremium' => $gremium]);
            }
            if (trim($q) !== '') {
                foreach (preg_split('/\s+/u', trim($q)) as $wort) {
                    $query->andWhere(['like', 'tagesordnung', $wort]);
                }
            }
            return $query;
        };
        $vergangen = $filter(Sitzung::vergangene());
        $anzahl = (int)(clone $vergangen)->count();
        $user = Yii::$app->user->getIdentity();
        $this->view->setPageTitle(Yii::t('StadtratModule.base', 'Stadtrat'));
        return $this->render('index', [
            'module' => $this->module,
            'kommende' => $filter(Sitzung::kommende())->all(),
            'vergangene' => $vergangen->limit($alle || trim($q) !== '' ? 200 : 6)->all(),
            'mehr' => $alle || trim($q) !== '' ? 0 : max(0, $anzahl - 6),
            'gremien' => Sitzung::find()->select('gremium')->distinct()->orderBy('gremium')->column(),
            'gremium' => $gremium,
            'q' => trim($q),
            'abo' => $user ? Ris::abo($user) : null,
            'stand' => Sitzung::find()->max('abgerufen_am'),
        ]);
    }

    /** Abo „Neue Tagesordnung“ an/aus */
    public function actionAbo()
    {
        $user = Yii::$app->user->getIdentity();
        if ($user === null || !Yii::$app->request->isPost) {
            throw new NotFoundHttpException();
        }
        Ris::setAbo($user, (bool)Yii::$app->request->post('abo'));
        $this->view->saved();
        return $this->redirect(Ris::url());
    }
}
