<?php

namespace humhub\modules\verzeichnis\controllers;

use humhub\modules\file\models\File;
use humhub\modules\user\components\PermissionManager;
use humhub\modules\user\models\User;
use humhub\modules\verzeichnis\models\VerwalterBasis;
use Yii;
use yii\web\ForbiddenHttpException;

/**
 * Für die Eintrags-Controller der Verzeichnisse (BetriebController, VereinController): Verwaltung anfragen,
 * Anfragen bestätigen/ablehnen, Verwalter entfernen, offene Anfragen, Logo und Bilder speichern.
 * Ansichten verwalter/anfragen liegen im Modul. Das Modul braucht die statische Methode canManageAll().
 */
trait VerwaltungTrait
{
    /** Eintrag, z. B. „betrieb“: Variable in den Ansichten, Spalte <name>_id, Relation am Verwalter */
    abstract protected function eintragName(): string;

    /** @return string Klasse des Eintrags (STATUS_VORGESCHLAGEN, STATUS_AKTIV, MAX_BILDER) */
    abstract protected function eintragKlasse(): string;

    /** @return string|VerwalterBasis */
    abstract protected function verwalterKlasse(): string;

    abstract protected function verwaltenRecht(): string;

    /** Benachrichtigungen (Klassen) „Verwaltung angefragt“ und „bestätigt“ */
    abstract protected function angefragtBenachrichtigung(): string;

    abstract protected function bestaetigtBenachrichtigung(): string;

    /** Übersetzungskategorie des Moduls */
    abstract protected function kategorie(): string;

    /** Übersetzte Texte, die den Eintrag nennen: nurVerwalterSehen, kannVerwalten (mit {name}) */
    abstract protected function verwaltungTexte(string $name = ''): array;

    /** Eintrag laden (vorgeschlagene nur für Verwalter und Anfragende), sonst 404 */
    abstract protected function findEintrag($id);

    /** Verwaltung anfragen */
    public function actionAnfrage($id)
    {
        $this->forcePostRequest();
        $eintrag = $this->findEintrag($id);
        if ($this->verwalterKlasse()::findOne([$this->eintragName() . '_id' => $eintrag->id, 'user_id' => Yii::$app->user->id])) {
            return $this->redirect($eintrag->getUrl());
        }
        $this->verwaltungAnfragen($eintrag, trim((string)Yii::$app->request->post('nachricht', '')));
        $this->view->success(Yii::t($this->kategorie(), 'Deine Anfrage wurde verschickt. Du bekommst eine Benachrichtigung, sobald sie bestätigt ist.'));
        return $this->redirect($eintrag->getUrl());
    }

    /** Verwalter eines Eintrags: Anfragen bestätigen/ablehnen, Verwalter entfernen */
    public function actionVerwalter($id)
    {
        $eintrag = $this->findEintrag($id);
        if (!$eintrag->canManage()) {
            throw new ForbiddenHttpException($this->verwaltungTexte()['nurVerwalterSehen']);
        }
        $eintragKlasse = $this->eintragKlasse();
        $verwalterKlasse = $this->verwalterKlasse();
        if (Yii::$app->request->isPost) {
            $row = $verwalterKlasse::findOne([$this->eintragName() . '_id' => $eintrag->id, 'user_id' => (int)Yii::$app->request->post('user_id')]);
            $aktion = Yii::$app->request->post('aktion');
            if ($row && $aktion === 'bestaetigen' && $row->status === VerwalterBasis::STATUS_ANGEFRAGT) {
                // Ein vorgeschlagener Eintrag wird erst durch Admins freigegeben
                if ($eintrag->status === $eintragKlasse::STATUS_VORGESCHLAGEN && !$this->module::canManageAll()) {
                    throw new ForbiddenHttpException();
                }
                $row->updateAttributes(['status' => VerwalterBasis::STATUS_BESTAETIGT]);
                if ($eintrag->status === $eintragKlasse::STATUS_VORGESCHLAGEN) {
                    $eintrag->updateAttributes(['status' => $eintragKlasse::STATUS_AKTIV]);
                }
                $this->bestaetigtBenachrichtigung()::instance()->from(Yii::$app->user->getIdentity())->about($eintrag)->send($row->user);
                $this->view->success($this->verwaltungTexte($row->user->displayName)['kannVerwalten']);
            } elseif ($row && in_array($aktion, ['ablehnen', 'entfernen'], true)) {
                $row->delete();
                $this->view->success($aktion === 'ablehnen' ? Yii::t($this->kategorie(), 'Anfrage abgelehnt.') : Yii::t($this->kategorie(), 'Verwalter entfernt.'));
                if ($row->user_id === Yii::$app->user->id && !$eintrag->canManage()) {
                    return $this->redirect($eintrag->getUrl());
                }
            }
            return $this->redirect($this->contentContainer->createUrl('/' . $this->module->id . '/' . $this->id . '/verwalter', ['id' => $eintrag->id]));
        }
        return $this->render('verwalter', [
            $this->eintragName() => $eintrag,
            'rows' => $eintrag->getVerwalter()->with('user')->orderBy(['status' => SORT_ASC, 'created_at' => SORT_ASC])->all(),
        ]);
    }

    /** Alle offenen Anfragen (Admins / Recht „… verwalten“) */
    public function actionAnfragen()
    {
        if (!$this->module::canManageAll()) {
            throw new ForbiddenHttpException();
        }
        return $this->render('anfragen', [
            'rows' => $this->verwalterKlasse()::find()->with(['user', $this->eintragName()])
                ->where(['status' => VerwalterBasis::STATUS_ANGEFRAGT])->orderBy(['created_at' => SORT_ASC])->all(),
        ]);
    }

    protected function verwaltungAnfragen($eintrag, string $nachricht): void
    {
        $verwalterKlasse = $this->verwalterKlasse();
        (new $verwalterKlasse([
            $this->eintragName() . '_id' => $eintrag->id,
            'user_id' => Yii::$app->user->id,
            'status' => VerwalterBasis::STATUS_ANGEFRAGT,
            'nachricht' => mb_substr($nachricht, 0, 1000),
        ]))->save(false);
        // Bisherige Verwalter (nur bei freigegebenen Einträgen) und alle mit „… verwalten“ bzw. Admins
        $recipients = $eintrag->status === $this->eintragKlasse()::STATUS_AKTIV ? $eintrag->getConfirmedUsers() : [];
        foreach (User::find()->active()->all() as $user) {
            if ($user->isSystemAdmin() || (new PermissionManager(['subject' => $user]))->can($this->verwaltenRecht())) {
                $recipients[] = $user;
            }
        }
        $byId = [];
        foreach ($recipients as $user) {
            if ($user->id !== Yii::$app->user->id) {
                $byId[$user->id] = $user;
            }
        }
        $this->angefragtBenachrichtigung()::instance()->from(Yii::$app->user->getIdentity())->about($eintrag)->sendBulk(array_values($byId));
    }

    /**
     * Logo: neu hochgeladenes Bild ersetzt das bisherige, „Logo entfernen“ löscht es.
     * Bilder: weitere hinzufügen (höchstens MAX_BILDER des Eintrags), angehakte entfernen.
     */
    protected function saveImages($eintrag): void
    {
        $request = Yii::$app->request;
        $logo = $eintrag->getLogo();
        $newLogo = array_values(array_filter((array)$request->post('fileList', [])));
        if ($logo && ($newLogo !== [] || $request->post('logoEntfernen'))) {
            $this->bildLoeschen($eintrag, $logo);
            $eintrag->updateAttributes(['logo_guid' => null] + $this->logoZusatz());
        }
        if ($newLogo !== []) {
            $eintrag->fileManager->attach([$newLogo[0]]);
            $eintrag->updateAttributes(['logo_guid' => $newLogo[0]] + $this->logoZusatz());
        }
        $remove = (array)$request->post('bildEntfernen', []);
        foreach ($eintrag->getGallery() as $file) {
            if (in_array($file->guid, $remove, true)) {
                $this->bildLoeschen($eintrag, $file);
            }
        }
        $free = $this->eintragKlasse()::MAX_BILDER - count($eintrag->getGallery());
        $new = array_slice(array_values(array_filter((array)$request->post('galerieList', []))), 0, max(0, $free));
        if ($new !== []) {
            $eintrag->fileManager->attach($new);
        }
    }

    /** Bild löschen; Vereine entfernen dabei auch den Bildnachweis */
    protected function bildLoeschen($eintrag, File $file): void
    {
        $file->delete();
    }

    /** Weitere Spalten, die sich mit dem Logo ändern (Vereine: logo_quelle → null, eigenes Logo) */
    protected function logoZusatz(): array
    {
        return [];
    }
}
