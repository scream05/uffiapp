<?php

namespace humhub\modules\vereine\controllers;

use humhub\modules\content\components\ContentContainerController;
use humhub\modules\user\models\User;
use humhub\modules\vereine\models\Verein;
use humhub\modules\vereine\models\VereinBeitrag;
use humhub\modules\vereine\models\VereinVerwalter;
use humhub\modules\vereine\Module;
use humhub\modules\vereine\notifications\VerwaltungAngefragt;
use humhub\modules\vereine\notifications\VerwaltungBestaetigt;
use Yii;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;

/**
 * Vereinsverzeichnis: Liste, Vorstellungsseite, Bearbeiten, Verwaltung anfragen/bestätigen.
 *
 * @property Module $module
 */
class VereinController extends ContentContainerController
{
    protected function getAccessRules()
    {
        return [['login' => ['create', 'edit', 'delete', 'anfrage', 'verwalter', 'anfragen']]]; // Verzeichnis + Vereinsseiten auch für Gäste
    }

    public function beforeAction($action)
    {
        // Lesbar für alle, aber nicht in Suchmaschinen (Namen und Kontaktdaten der Ansprechpartner)
        Yii::$app->response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        return parent::beforeAction($action);
    }

    public function actionIndex($q = '', $kategorie = '', $ortsteil = '', $keyword = '')
    {
        $q = $q !== '' ? $q : $keyword; // „Alle Ergebnisse anzeigen“ der Suche in der Kopfleiste
        $query = Verein::find()->orderBy(['name' => SORT_ASC]);
        if (!Module::canManageAll()) {
            $query->andWhere(['!=', 'status', Verein::STATUS_VORGESCHLAGEN]);
        }
        if (trim($q) !== '') {
            $query->andWhere(Verein::searchCondition($q));
        }
        if (in_array($kategorie, $this->module->categories, true)) {
            $query->andWhere(['kategorie' => $kategorie]);
        }
        if (in_array($ortsteil, $this->module->ortsteile, true)) {
            $query->andWhere(['ortsteil' => $ortsteil]);
        }
        $mine = Verein::find()->innerJoin('verein_verwalter vv', 'vv.verein_id = verein.id')
            ->where(['vv.user_id' => Yii::$app->user->id, 'vv.status' => VereinVerwalter::STATUS_BESTAETIGT])
            ->orderBy(['name' => SORT_ASC])->all();
        // Admins sehen in der Liste den ersten (am längsten bestätigten) Verwalter je Verein
        $verwalter = [];
        if (Module::canManageAll()) {
            foreach (VereinVerwalter::find()->with('user')->where(['status' => VereinVerwalter::STATUS_BESTAETIGT])
                         ->orderBy(['created_at' => SORT_ASC, 'user_id' => SORT_ASC])->all() as $row) {
                if ($row->user && !isset($verwalter[$row->verein_id])) {
                    $verwalter[$row->verein_id] = $row->user->displayName;
                }
            }
        }
        return $this->render('index', [
            'vereine' => $query->all(),
            'verwalter' => $verwalter,
            'mine' => $mine,
            'q' => $q,
            'kategorie' => $kategorie,
            'ortsteil' => $ortsteil,
            'module' => $this->module,
        ]);
    }

    public function actionView($id)
    {
        $verein = $this->findVerein($id);
        $own = VereinVerwalter::findOne(['verein_id' => $verein->id, 'user_id' => Yii::$app->user->id]);
        return $this->render('view', [
            'verein' => $verein,
            'own' => $own,
            'canManage' => $verein->canManage(),
            'entries' => $verein->getUpcomingEntries(),
            'calendarSpace' => $this->module->getCalendarSpace(),
            'beitraege' => VereinBeitrag::find()->where(['verein_id' => $verein->id, 'status' => VereinBeitrag::STATUS_VEROEFFENTLICHT,
                'auf_vereinsseite' => 1])->orderBy(['geprueft_am' => SORT_DESC, 'id' => SORT_DESC])->limit(20)->all(),
            // Eingereichte und (letzte 30 Tage) abgelehnte Beiträge sehen die Verwalter des Vereins
            'eigene' => $verein->canManage() ? VereinBeitrag::find()->with('user')->where(['verein_id' => $verein->id])
                ->andWhere(['or', ['status' => VereinBeitrag::STATUS_EINGEREICHT],
                    ['and', ['status' => VereinBeitrag::STATUS_ABGELEHNT], ['>=', 'geprueft_am', date('Y-m-d H:i:s', strtotime('-30 days'))]]])
                ->orderBy(['created_at' => SORT_DESC])->all() : [],
        ]);
    }

    /** Neuer Verein: Vorschlag von Nutzern (Freigabe durch Admin), direkt aktiv bei „Vereine verwalten“ */
    public function actionCreate()
    {
        $verein = new Verein(['ortsteil' => 'Uffenheim']);
        $nachricht = '';
        if ($verein->load(Yii::$app->request->post())) {
            $verein->status = Module::canManageAll() ? Verein::STATUS_AKTIV : Verein::STATUS_VORGESCHLAGEN;
            $nachricht = trim((string)Yii::$app->request->post('nachricht', ''));
            if ($verein->save()) {
                $this->saveImages($verein);
                if (!Module::canManageAll()) {
                    $this->request($verein, $nachricht);
                    $this->view->success(Yii::t('VereineModule.base', 'Danke! Dein Vorschlag wird geprüft. Sobald er freigegeben ist, kannst du die Seite pflegen.'));
                }
                return $this->redirect($verein->getUrl());
            }
        }
        return $this->render('edit', ['verein' => $verein, 'isNew' => true, 'nachricht' => $nachricht, 'module' => $this->module]);
    }

    public function actionEdit($id)
    {
        $verein = $this->findVerein($id);
        if (!$verein->canManage()) {
            throw new ForbiddenHttpException(Yii::t('VereineModule.base', 'Nur Verwalter dieses Vereins können die Seite bearbeiten.'));
        }
        if ($verein->load(Yii::$app->request->post())) {
            // Umbenennen nur durch Admins (der Name ist das Erkennungsmerkmal im Verzeichnis)
            if (!Module::canManageAll()) {
                $verein->name = $verein->getOldAttribute('name');
            }
            if ($verein->status === Verein::STATUS_OFFEN) {
                $verein->status = Verein::STATUS_AKTIV;
            }
            if ($verein->save()) {
                $this->saveImages($verein);
                $this->view->saved();
                return $this->redirect($verein->getUrl());
            }
        }
        return $this->render('edit', ['verein' => $verein, 'isNew' => false, 'nachricht' => '', 'module' => $this->module]);
    }

    public function actionDelete($id)
    {
        $this->forcePostRequest();
        if (!Module::canManageAll()) {
            throw new ForbiddenHttpException();
        }
        $verein = $this->findVerein($id);
        $verein->delete();
        $this->view->success(Yii::t('VereineModule.base', '„{name}“ wurde aus dem Verzeichnis entfernt. Bereits eingetragene Termine bleiben im Kalender.', ['name' => $verein->name]));
        return $this->redirect($this->contentContainer->createUrl('/vereine/verein/index'));
    }

    /** Verwaltung anfragen (Vorstandsmitglied o. Ä.) */
    public function actionAnfrage($id)
    {
        $this->forcePostRequest();
        $verein = $this->findVerein($id);
        if (VereinVerwalter::findOne(['verein_id' => $verein->id, 'user_id' => Yii::$app->user->id])) {
            return $this->redirect($verein->getUrl());
        }
        $this->request($verein, trim((string)Yii::$app->request->post('nachricht', '')));
        $this->view->success(Yii::t('VereineModule.base', 'Deine Anfrage wurde verschickt. Du bekommst eine Benachrichtigung, sobald sie bestätigt ist.'));
        return $this->redirect($verein->getUrl());
    }

    /** Verwalter eines Vereins: Anfragen bestätigen/ablehnen, Verwalter entfernen */
    public function actionVerwalter($id)
    {
        $verein = $this->findVerein($id);
        if (!$verein->canManage()) {
            throw new ForbiddenHttpException(Yii::t('VereineModule.base', 'Nur Verwalter dieses Vereins können das sehen.'));
        }
        if (Yii::$app->request->isPost) {
            $userId = (int)Yii::$app->request->post('user_id');
            $row = VereinVerwalter::findOne(['verein_id' => $verein->id, 'user_id' => $userId]);
            $action = Yii::$app->request->post('aktion');
            if ($row && $action === 'bestaetigen' && $row->status === VereinVerwalter::STATUS_ANGEFRAGT) {
                $row->updateAttributes(['status' => VereinVerwalter::STATUS_BESTAETIGT]);
                if ($verein->status === Verein::STATUS_VORGESCHLAGEN && Module::canManageAll()) {
                    $verein->updateAttributes(['status' => Verein::STATUS_AKTIV]);
                }
                VerwaltungBestaetigt::instance()->from(Yii::$app->user->getIdentity())->about($verein)->send($row->user);
                $this->view->success(Yii::t('VereineModule.base', '{name} kann den Verein jetzt verwalten.', ['name' => $row->user->displayName]));
            } elseif ($row && in_array($action, ['ablehnen', 'entfernen'], true)) {
                $row->delete();
                $this->view->success($action === 'ablehnen' ? Yii::t('VereineModule.base', 'Anfrage abgelehnt.') : Yii::t('VereineModule.base', 'Verwalter entfernt.'));
                if ($row->user_id === Yii::$app->user->id && !$verein->canManage()) {
                    return $this->redirect($verein->getUrl());
                }
            }
            return $this->redirect($this->contentContainer->createUrl('/vereine/verein/verwalter', ['id' => $verein->id]));
        }
        return $this->render('verwalter', [
            'verein' => $verein,
            'rows' => $verein->getVerwalter()->with('user')->orderBy(['status' => SORT_ASC, 'created_at' => SORT_ASC])->all(),
        ]);
    }

    /** Alle offenen Anfragen (Admins / Recht „Vereine verwalten“) */
    public function actionAnfragen()
    {
        if (!Module::canManageAll()) {
            throw new ForbiddenHttpException();
        }
        return $this->render('anfragen', [
            'rows' => VereinVerwalter::find()->with(['user', 'verein'])
                ->where(['status' => VereinVerwalter::STATUS_ANGEFRAGT])->orderBy(['created_at' => SORT_ASC])->all(),
        ]);
    }

    private function request(Verein $verein, string $nachricht): void
    {
        $row = new VereinVerwalter([
            'verein_id' => $verein->id,
            'user_id' => Yii::$app->user->id,
            'status' => VereinVerwalter::STATUS_ANGEFRAGT,
            'nachricht' => mb_substr($nachricht, 0, 1000),
        ]);
        $row->save(false);
        // Bisherige Verwalter des Vereins und alle mit „Vereine verwalten“ bzw. Admins
        $recipients = $verein->getConfirmedUsers();
        foreach (User::find()->active()->all() as $user) {
            if ($user->isSystemAdmin() || (new \humhub\modules\user\components\PermissionManager(['subject' => $user]))
                    ->can(\humhub\modules\vereine\permissions\ManageVereine::class)) {
                $recipients[] = $user;
            }
        }
        $byId = [];
        foreach ($recipients as $user) {
            if ($user->id !== Yii::$app->user->id) {
                $byId[$user->id] = $user;
            }
        }
        VerwaltungAngefragt::instance()->from(Yii::$app->user->getIdentity())->about($verein)->sendBulk(array_values($byId));
    }

    /**
     * Logo: neu hochgeladenes Bild ersetzt das bisherige, „Logo entfernen“ löscht es.
     * Galerie: weitere Bilder hinzufügen (höchstens Verein::MAX_BILDER), angehakte entfernen.
     */
    private function saveImages(Verein $verein): void
    {
        $request = Yii::$app->request;
        $logo = $verein->getLogo();
        $newLogo = array_values(array_filter((array)$request->post('fileList', [])));
        if ($logo && ($newLogo !== [] || $request->post('logoEntfernen'))) {
            $this->deleteImage($verein, $logo);
            $verein->updateAttributes(['logo_guid' => null, 'logo_quelle' => null]);
        }
        if ($newLogo !== []) {
            $verein->fileManager->attach([$newLogo[0]]);
            $verein->updateAttributes(['logo_guid' => $newLogo[0], 'logo_quelle' => null]);
        }
        $remove = (array)$request->post('bildEntfernen', []);
        foreach ($verein->getGallery() as $file) {
            if (in_array($file->guid, $remove, true)) {
                $this->deleteImage($verein, $file);
            }
        }
        $free = Verein::MAX_BILDER - count($verein->getGallery());
        $new = array_slice(array_values(array_filter((array)$request->post('galerieList', []))), 0, max(0, $free));
        if ($new !== []) {
            $verein->fileManager->attach($new);
        }
    }

    private function deleteImage(Verein $verein, \humhub\modules\file\models\File $file): void
    {
        $credits = $verein->getBildnachweise();
        unset($credits[$file->guid]);
        $verein->updateAttributes(['bildnachweise' => $credits ? json_encode($credits, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null]);
        $file->delete();
    }

    private function findVerein($id): Verein
    {
        $verein = Verein::findOne((int)$id);
        if ($verein === null || ($verein->status === Verein::STATUS_VORGESCHLAGEN && !$verein->canManage()
                && !VereinVerwalter::find()->where(['verein_id' => $verein->id, 'user_id' => Yii::$app->user->id])->exists())) {
            throw new NotFoundHttpException(Yii::t('VereineModule.base', 'Verein nicht gefunden.'));
        }
        return $verein;
    }
}
