<?php

namespace humhub\modules\verzeichnis\controllers;

use humhub\modules\content\components\ContentContainerController;
use humhub\modules\verzeichnis\components\BeitragDienstBasis;
use humhub\modules\verzeichnis\models\BeitragBasis;
use Yii;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;

/**
 * Beiträge eines Verzeichnisses: Verwalter schreiben, Admins (bzw. „… verwalten“) prüfen, geben frei oder lehnen ab.
 * Je Modul eine Unterklasse (gewerbe/vereine controllers\BeitragController); Ansichten create/pruefen liegen im Modul.
 */
abstract class BeitragControllerBasis extends ContentContainerController
{
    /** @return string|BeitragBasis Klasse des Beitrags */
    abstract protected function beitragKlasse(): string;

    /** @return string Klasse des Eintrags (Betrieb, Verein) mit STATUS_VORGESCHLAGEN und canManage() */
    abstract protected function eintragKlasse(): string;

    /** @return string|BeitragDienstBasis Klasse des Dienstes */
    abstract protected function dienstKlasse(): string;

    /**
     * Übersetzte Texte, die den Eintrag nennen: nichtGefunden, nurVerwalter, ortWaehlen, beide, seite, uebersicht,
     * ausUebersicht (Literale je Modul, damit sie in dessen messages/ stehen)
     */
    abstract protected function texte(): array;

    protected function getAccessRules()
    {
        return [['login']];
    }

    /** Text in der Kategorie des Moduls (gleich lautende Texte beider Verzeichnisse) */
    protected function t(string $text, array $params = []): string
    {
        return Yii::t($this->beitragKlasse()::kategorie(), $text, $params);
    }

    /** Beitrag schreiben (bestätigte Verwalter des Eintrags); Parameter ?betrieb= bzw. ?verein= */
    public function actionCreate()
    {
        $rel = $this->beitragKlasse()::eintragRelation();
        $eintragKlasse = $this->eintragKlasse();
        $v = $eintragKlasse::findOne((int)Yii::$app->request->get($rel));
        if ($v === null || $v->status === $eintragKlasse::STATUS_VORGESCHLAGEN) {
            throw new NotFoundHttpException($this->texte()['nichtGefunden']);
        }
        if (!$v->canManage()) {
            throw new ForbiddenHttpException($this->texte()['nurVerwalter']);
        }
        $klasse = $this->beitragKlasse();
        $beitrag = new $klasse([$rel . '_id' => $v->id, 'user_id' => Yii::$app->user->id, 'status' => BeitragBasis::STATUS_EINGEREICHT]);
        if ($beitrag->load(Yii::$app->request->post()) && $beitrag->save()) {
            $bilder = array_slice(array_values(array_filter((array)Yii::$app->request->post('bilderList', []))), 0, $klasse::MAX_BILDER);
            if ($bilder) {
                $beitrag->fileManager->attach($bilder);
            }
            $this->dienstKlasse()::eingereicht($beitrag);
            $this->view->success($this->t('Danke! Dein Beitrag wird geprüft und danach veröffentlicht. Du bekommst Bescheid.'));
            return $this->redirect($v->getUrl());
        }
        return $this->render('create', [$rel => $v, 'beitrag' => $beitrag]);
    }

    /** Eingereichte Beiträge prüfen */
    public function actionPruefen()
    {
        $this->nurPruefer();
        $klasse = $this->beitragKlasse();
        $with = [$klasse::eintragRelation(), 'user'];
        return $this->render('pruefen', [
            'offen' => $klasse::find()->with($with)->where(['status' => BeitragBasis::STATUS_EINGEREICHT])
                ->orderBy(['created_at' => SORT_ASC])->all(),
            'erledigt' => $klasse::find()->with($with)->where(['!=', 'status', BeitragBasis::STATUS_EINGEREICHT])
                ->orderBy(['geprueft_am' => SORT_DESC])->limit(20)->all(),
        ]);
    }

    /** Freigeben (Eintragsseite und/oder Übersicht) oder ablehnen mit Begründung */
    public function actionEntscheiden($id)
    {
        $this->forcePostRequest();
        $this->nurPruefer();
        $b = $this->finde($id);
        $zurueck = $this->pruefenUrl();
        if ($b->status !== BeitragBasis::STATUS_EINGEREICHT) {
            $this->view->info($this->t('Dieser Beitrag wurde schon bearbeitet.'));
            return $this->redirect($zurueck);
        }
        $request = Yii::$app->request;
        if ($request->post('aktion') === 'ablehnen') {
            $begruendung = trim((string)$request->post('begruendung', ''));
            if ($begruendung === '') {
                $this->view->error($this->t('Bitte eine Begründung für die Ablehnung angeben – sie geht per E-Mail an den Verfasser.'));
                return $this->redirect($zurueck);
            }
            $ok = $this->dienstKlasse()::ablehnen($b, $begruendung);
            $ok ? $this->view->success($this->t('Abgelehnt. {name} hat die Begründung per E-Mail bekommen.', ['name' => $b->user->displayName]))
                : $this->view->error($this->t('Abgelehnt, aber die E-Mail an den Verfasser konnte nicht verschickt werden.'));
            return $this->redirect($zurueck);
        }
        if ($request->post('aktion') !== 'freigeben') {
            throw new BadRequestHttpException($this->t('Unbekannte Aktion.'));
        }
        // Formularfeld „betriebsseite“ bzw. „vereinsseite“ (Spaltenname ohne „auf_“)
        $seite = (bool)$request->post(substr($b::seiteSpalte(), 4));
        $uebersicht = (bool)$request->post('uebersicht');
        if (!$seite && !$uebersicht) {
            $this->view->error($this->texte()['ortWaehlen']);
            return $this->redirect($zurueck);
        }
        $this->dienstKlasse()::freigeben($b, $seite, $uebersicht);
        $texte = $this->texte();
        $this->view->success($seite && $uebersicht ? $texte['beide'] : ($seite ? $texte['seite'] : $texte['uebersicht']));
        return $this->redirect($zurueck);
    }

    /** Schon veröffentlichten Beitrag (nur Eintragsseite) nachträglich auch in der Übersicht zeigen */
    public function actionUebersicht($id)
    {
        $this->forcePostRequest();
        $this->nurPruefer();
        $b = $this->finde($id);
        if ($b->status !== BeitragBasis::STATUS_VEROEFFENTLICHT || $b->content_id) {
            $this->view->info($this->t('Dieser Beitrag ist schon in der Übersicht oder nicht veröffentlicht.'));
        } else {
            $this->dienstKlasse()::freigeben($b, (bool)$b->{$b::seiteSpalte()}, true, false);
            $this->view->success($this->t('Der Beitrag steht jetzt auch in der Übersicht.'));
        }
        return $this->redirect($this->zurueck($b->eintrag));
    }

    /** Aus der Übersicht nehmen, auf der Eintragsseite lassen */
    public function actionAusUebersicht($id)
    {
        $this->forcePostRequest();
        $this->nurPruefer();
        $b = $this->finde($id);
        if ($b->content_id) {
            $this->dienstKlasse()::ausUebersicht($b);
            $this->view->success($this->texte()['ausUebersicht']);
        }
        return $this->redirect($this->zurueck($b->eintrag));
    }

    /** Veröffentlichten oder abgelehnten Beitrag entfernen (samt HumHub-Beitrag) */
    public function actionEntfernen($id)
    {
        $this->forcePostRequest();
        $this->nurPruefer();
        $b = $this->finde($id);
        $eintrag = $b->eintrag;
        $this->dienstKlasse()::entfernen($b);
        $this->view->success($this->t('Beitrag entfernt.'));
        return $this->redirect($this->zurueck($eintrag));
    }

    /** Zurück zur Eintragsseite (POST zurueck=betrieb bzw. verein) oder zur Prüfliste */
    protected function zurueck($eintrag): string
    {
        return Yii::$app->request->post('zurueck') === $this->beitragKlasse()::eintragRelation() && $eintrag !== null
            ? $eintrag->getUrl() : $this->pruefenUrl();
    }

    protected function pruefenUrl(): string
    {
        return $this->contentContainer->createUrl('/' . $this->module->id . '/beitrag/pruefen');
    }

    protected function nurPruefer(): void
    {
        if (!$this->beitragKlasse()::darfAllesVerwalten()) {
            throw new ForbiddenHttpException();
        }
    }

    protected function finde($id): BeitragBasis
    {
        $b = $this->beitragKlasse()::findOne((int)$id);
        if ($b === null) {
            throw new NotFoundHttpException($this->t('Beitrag nicht gefunden.'));
        }
        return $b;
    }
}
