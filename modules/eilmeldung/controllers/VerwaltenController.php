<?php

namespace humhub\modules\eilmeldung\controllers;

use humhub\components\access\ControllerAccess;
use humhub\components\Controller;
use humhub\modules\eilmeldung\components\Versand;
use humhub\modules\eilmeldung\models\Eilmeldung;
use Yii;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;

/** /eilmeldung/verwalten – Eilmeldung schreiben (Push an alle Empfänger) und beenden; nur Admins und Bauhof */
class VerwaltenController extends Controller
{
    /** Wählbare Gültigkeit in Stunden */
    public const DAUER = [3, 6, 12, 24, 48, 72, 168];

    protected function getAccessRules()
    {
        return [[ControllerAccess::RULE_LOGGED_IN_ONLY], [ControllerAccess::RULE_POST => ['beenden']]];
    }

    public function beforeAction($action)
    {
        if (!$this->module->darfSchreiben(Yii::$app->user->getIdentity())) {
            throw new ForbiddenHttpException();
        }
        return parent::beforeAction($action);
    }

    public function actionIndex()
    {
        $e = new Eilmeldung(['art' => 'sperrung']);
        $r = Yii::$app->request;
        $dauer = (int)$r->post('dauer', 24);
        if ($r->isPost && $e->load($r->post())) {
            $e->gueltig_bis = date('Y-m-d H:i:s', strtotime('+' . (in_array($dauer, self::DAUER, true) ? $dauer : 24) . ' hours'));
            $e->quelle = 'hand';
            $e->created_by = Yii::$app->user->id;
            $e->created_at = date('Y-m-d H:i:s');
            if ($e->save()) {
                $n = $r->post('push') ? Versand::senden($e) : 0;
                Versand::geaendert();
                $this->view->success($n
                    ? Yii::t('EilmeldungModule.base', 'Eilmeldung veröffentlicht und als Push an {n} Konten geschickt.', ['n' => $n])
                    : Yii::t('EilmeldungModule.base', 'Eilmeldung veröffentlicht (ohne Push).'));
                return $this->redirect(['index']);
            }
        }
        return $this->render('index', [
            'model' => $e,
            'dauer' => $dauer,
            'aktiv' => Eilmeldung::aktiv()->all(),
            'module' => $this->module,
            'empfaenger' => (int)Versand::empfaenger()->count(),
        ]);
    }

    public function actionBeenden($id)
    {
        $e = Eilmeldung::findOne(['id' => (int)$id]);
        if ($e === null) {
            throw new NotFoundHttpException();
        }
        $e->updateAttributes(['beendet_am' => date('Y-m-d H:i:s')]);
        Versand::geaendert();
        $this->view->success(Yii::t('EilmeldungModule.base', 'Eilmeldung beendet.'));
        return $this->redirect(['index']);
    }
}
