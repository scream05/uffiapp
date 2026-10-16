<?php

namespace humhub\modules\gewerbe\controllers;

use humhub\modules\content\components\ContentContainerController;
use humhub\modules\gewerbe\components\BeitragDienst;
use humhub\modules\gewerbe\models\Betrieb;
use humhub\modules\gewerbe\models\BetriebBeitrag;
use humhub\modules\gewerbe\Module;
use Yii;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;

/**
 * Gewerbebeiträge: Verwalter schreiben, Admins (bzw. „Gewerbe verwalten“) prüfen, geben frei oder lehnen ab.
 *
 * @property Module $module
 */
class BeitragController extends ContentContainerController
{
    protected function getAccessRules()
    {
        return [['login']];
    }

    /** Beitrag schreiben (bestätigte Verwalter des Betriebs) */
    public function actionCreate($betrieb)
    {
        $v = Betrieb::findOne((int)$betrieb);
        if ($v === null || $v->status === Betrieb::STATUS_VORGESCHLAGEN) {
            throw new NotFoundHttpException('Betrieb nicht gefunden.');
        }
        if (!$v->canManage()) {
            throw new ForbiddenHttpException('Nur Verwalter dieses Betriebs können Beiträge schreiben.');
        }
        $beitrag = new BetriebBeitrag(['betrieb_id' => $v->id, 'user_id' => Yii::$app->user->id, 'status' => BetriebBeitrag::STATUS_EINGEREICHT]);
        if ($beitrag->load(Yii::$app->request->post()) && $beitrag->save()) {
            $bilder = array_slice(array_values(array_filter((array)Yii::$app->request->post('bilderList', []))), 0, BetriebBeitrag::MAX_BILDER);
            if ($bilder) {
                $beitrag->fileManager->attach($bilder);
            }
            BeitragDienst::eingereicht($beitrag);
            $this->view->success('Danke! Dein Beitrag wird geprüft und danach veröffentlicht. Du bekommst Bescheid.');
            return $this->redirect($v->getUrl());
        }
        return $this->render('create', ['betrieb' => $v, 'beitrag' => $beitrag]);
    }

    /** Eingereichte Beiträge prüfen */
    public function actionPruefen()
    {
        $this->nurPruefer();
        return $this->render('pruefen', [
            'offen' => BetriebBeitrag::find()->with(['betrieb', 'user'])->where(['status' => BetriebBeitrag::STATUS_EINGEREICHT])
                ->orderBy(['created_at' => SORT_ASC])->all(),
            'erledigt' => BetriebBeitrag::find()->with(['betrieb', 'user'])->where(['!=', 'status', BetriebBeitrag::STATUS_EINGEREICHT])
                ->orderBy(['geprueft_am' => SORT_DESC])->limit(20)->all(),
        ]);
    }

    /** Freigeben (Betriebsseite und/oder Übersicht) oder ablehnen mit Begründung */
    public function actionEntscheiden($id)
    {
        $this->forcePostRequest();
        $this->nurPruefer();
        $b = $this->finde($id);
        $zurueck = $this->contentContainer->createUrl('/gewerbe/beitrag/pruefen');
        if ($b->status !== BetriebBeitrag::STATUS_EINGEREICHT) {
            $this->view->info('Dieser Beitrag wurde schon bearbeitet.');
            return $this->redirect($zurueck);
        }
        $request = Yii::$app->request;
        if ($request->post('aktion') === 'ablehnen') {
            $begruendung = trim((string)$request->post('begruendung', ''));
            if ($begruendung === '') {
                $this->view->error('Bitte eine Begründung für die Ablehnung angeben – sie geht per E-Mail an den Verfasser.');
                return $this->redirect($zurueck);
            }
            $ok = BeitragDienst::ablehnen($b, $begruendung);
            $ok ? $this->view->success("Abgelehnt. {$b->user->displayName} hat die Begründung per E-Mail bekommen.")
                : $this->view->error('Abgelehnt, aber die E-Mail an den Verfasser konnte nicht verschickt werden.');
            return $this->redirect($zurueck);
        }
        if ($request->post('aktion') !== 'freigeben') {
            throw new \yii\web\BadRequestHttpException('Unbekannte Aktion.');
        }
        $betriebsseite = (bool)$request->post('betriebsseite');
        $uebersicht = (bool)$request->post('uebersicht');
        if (!$betriebsseite && !$uebersicht) {
            $this->view->error('Bitte wählen, wo der Beitrag erscheinen soll: Betriebsseite und/oder Übersicht.');
            return $this->redirect($zurueck);
        }
        BeitragDienst::freigeben($b, $betriebsseite, $uebersicht);
        $this->view->success('Veröffentlicht' . ($betriebsseite && $uebersicht ? ' auf der Betriebsseite und in der Übersicht.'
                : ($betriebsseite ? ' auf der Betriebsseite.' : ' in der Übersicht.')));
        return $this->redirect($zurueck);
    }

    /** Schon veröffentlichten Beitrag (nur Betriebsseite) nachträglich auch in der Übersicht zeigen */
    public function actionUebersicht($id)
    {
        $this->forcePostRequest();
        $this->nurPruefer();
        $b = $this->finde($id);
        if ($b->status !== BetriebBeitrag::STATUS_VEROEFFENTLICHT || $b->content_id) {
            $this->view->info('Dieser Beitrag ist schon in der Übersicht oder nicht veröffentlicht.');
        } else {
            BeitragDienst::freigeben($b, (bool)$b->auf_betriebsseite, true, false);
            $this->view->success('Der Beitrag steht jetzt auch in der Übersicht.');
        }
        return $this->redirect(Yii::$app->request->post('zurueck') === 'betrieb' ? $b->betrieb->getUrl()
            : $this->contentContainer->createUrl('/gewerbe/beitrag/pruefen'));
    }

    /** Veröffentlichten oder abgelehnten Beitrag entfernen (samt HumHub-Beitrag) */
    public function actionEntfernen($id)
    {
        $this->forcePostRequest();
        $this->nurPruefer();
        $b = $this->finde($id);
        $betrieb = $b->betrieb;
        BeitragDienst::entfernen($b);
        $this->view->success('Beitrag entfernt.');
        return $this->redirect(Yii::$app->request->post('zurueck') === 'betrieb' ? $betrieb->getUrl()
            : $this->contentContainer->createUrl('/gewerbe/beitrag/pruefen'));
    }

    private function nurPruefer(): void
    {
        if (!Module::canManageAll()) {
            throw new ForbiddenHttpException();
        }
    }

    private function finde($id): BetriebBeitrag
    {
        $b = BetriebBeitrag::findOne((int)$id);
        if ($b === null) {
            throw new NotFoundHttpException('Beitrag nicht gefunden.');
        }
        return $b;
    }
}
