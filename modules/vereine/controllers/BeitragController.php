<?php

namespace humhub\modules\vereine\controllers;

use humhub\modules\content\components\ContentContainerController;
use humhub\modules\vereine\components\BeitragDienst;
use humhub\modules\vereine\models\Verein;
use humhub\modules\vereine\models\VereinBeitrag;
use humhub\modules\vereine\Module;
use Yii;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;

/**
 * Vereinsbeiträge: Verwalter schreiben, Admins (bzw. „Vereine verwalten“) prüfen, geben frei oder lehnen ab.
 *
 * @property Module $module
 */
class BeitragController extends ContentContainerController
{
    protected function getAccessRules()
    {
        return [['login']];
    }

    /** Beitrag schreiben (bestätigte Verwalter des Vereins) */
    public function actionCreate($verein)
    {
        $v = Verein::findOne((int)$verein);
        if ($v === null || $v->status === Verein::STATUS_VORGESCHLAGEN) {
            throw new NotFoundHttpException('Verein nicht gefunden.');
        }
        if (!$v->canManage()) {
            throw new ForbiddenHttpException('Nur Verwalter dieses Vereins können Beiträge schreiben.');
        }
        $beitrag = new VereinBeitrag(['verein_id' => $v->id, 'user_id' => Yii::$app->user->id, 'status' => VereinBeitrag::STATUS_EINGEREICHT]);
        if ($beitrag->load(Yii::$app->request->post()) && $beitrag->save()) {
            $bilder = array_slice(array_values(array_filter((array)Yii::$app->request->post('bilderList', []))), 0, VereinBeitrag::MAX_BILDER);
            if ($bilder) {
                $beitrag->fileManager->attach($bilder);
            }
            BeitragDienst::eingereicht($beitrag);
            $this->view->success('Danke! Dein Beitrag wird geprüft und danach veröffentlicht. Du bekommst Bescheid.');
            return $this->redirect($v->getUrl());
        }
        return $this->render('create', ['verein' => $v, 'beitrag' => $beitrag]);
    }

    /** Eingereichte Beiträge prüfen */
    public function actionPruefen()
    {
        $this->nurPruefer();
        return $this->render('pruefen', [
            'offen' => VereinBeitrag::find()->with(['verein', 'user'])->where(['status' => VereinBeitrag::STATUS_EINGEREICHT])
                ->orderBy(['created_at' => SORT_ASC])->all(),
            'erledigt' => VereinBeitrag::find()->with(['verein', 'user'])->where(['!=', 'status', VereinBeitrag::STATUS_EINGEREICHT])
                ->orderBy(['geprueft_am' => SORT_DESC])->limit(20)->all(),
        ]);
    }

    /** Freigeben (Vereinsseite und/oder Übersicht) oder ablehnen mit Begründung */
    public function actionEntscheiden($id)
    {
        $this->forcePostRequest();
        $this->nurPruefer();
        $b = $this->finde($id);
        $zurueck = $this->contentContainer->createUrl('/vereine/beitrag/pruefen');
        if ($b->status !== VereinBeitrag::STATUS_EINGEREICHT) {
            $this->view->info('Dieser Beitrag wurde schon bearbeitet.');
            return $this->redirect($zurueck);
        }
        $request = Yii::$app->request;
        if ($request->post('aktion') === 'ablehnen') {
            $begruendung = trim((string)$request->post('begruendung', ''));
            if ($begruendung === '') {
                $this->view->error('Bitte eine Begründung für die Ablehnung angeben – sie geht per Mail an den Verfasser.');
                return $this->redirect($zurueck);
            }
            $ok = BeitragDienst::ablehnen($b, $begruendung);
            $ok ? $this->view->success("Abgelehnt. {$b->user->displayName} hat die Begründung per Mail bekommen.")
                : $this->view->error('Abgelehnt, aber die Mail an den Verfasser konnte nicht verschickt werden.');
            return $this->redirect($zurueck);
        }
        if ($request->post('aktion') !== 'freigeben') {
            throw new \yii\web\BadRequestHttpException('Unbekannte Aktion.');
        }
        $vereinsseite = (bool)$request->post('vereinsseite');
        $uebersicht = (bool)$request->post('uebersicht');
        if (!$vereinsseite && !$uebersicht) {
            $this->view->error('Bitte wählen, wo der Beitrag erscheinen soll: Vereinsseite und/oder Übersicht.');
            return $this->redirect($zurueck);
        }
        BeitragDienst::freigeben($b, $vereinsseite, $uebersicht);
        $this->view->success('Veröffentlicht' . ($vereinsseite && $uebersicht ? ' auf der Vereinsseite und in der Übersicht.'
                : ($vereinsseite ? ' auf der Vereinsseite.' : ' in der Übersicht.')));
        return $this->redirect($zurueck);
    }

    /** Veröffentlichten oder abgelehnten Beitrag entfernen (samt HumHub-Beitrag) */
    public function actionEntfernen($id)
    {
        $this->forcePostRequest();
        $this->nurPruefer();
        $b = $this->finde($id);
        $verein = $b->verein;
        BeitragDienst::entfernen($b);
        $this->view->success('Beitrag entfernt.');
        return $this->redirect(Yii::$app->request->post('zurueck') === 'verein' ? $verein->getUrl()
            : $this->contentContainer->createUrl('/vereine/beitrag/pruefen'));
    }

    private function nurPruefer(): void
    {
        if (!Module::canManageAll()) {
            throw new ForbiddenHttpException();
        }
    }

    private function finde($id): VereinBeitrag
    {
        $b = VereinBeitrag::findOne((int)$id);
        if ($b === null) {
            throw new NotFoundHttpException('Beitrag nicht gefunden.');
        }
        return $b;
    }
}
