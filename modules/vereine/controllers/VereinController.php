<?php

namespace humhub\modules\vereine\controllers;

use humhub\modules\content\components\ContentContainerController;
use humhub\modules\file\models\File;
use humhub\modules\vereine\models\Verein;
use humhub\modules\vereine\models\VereinBeitrag;
use humhub\modules\vereine\models\VereinVerwalter;
use humhub\modules\vereine\Module;
use humhub\modules\vereine\notifications\VerwaltungAngefragt;
use humhub\modules\vereine\notifications\VerwaltungBestaetigt;
use humhub\modules\vereine\permissions\ManageVereine;
use humhub\modules\verzeichnis\controllers\VerwaltungTrait;
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
    use VerwaltungTrait;

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
                    $this->verwaltungAnfragen($verein, $nachricht);
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

    // Verwaltung anfragen/bestätigen, offene Anfragen, Logo und Bilder: VerwaltungTrait (modules/verzeichnis)

    protected function eintragName(): string
    {
        return 'verein';
    }

    protected function eintragKlasse(): string
    {
        return Verein::class;
    }

    protected function verwalterKlasse(): string
    {
        return VereinVerwalter::class;
    }

    protected function verwaltenRecht(): string
    {
        return ManageVereine::class;
    }

    protected function angefragtBenachrichtigung(): string
    {
        return VerwaltungAngefragt::class;
    }

    protected function bestaetigtBenachrichtigung(): string
    {
        return VerwaltungBestaetigt::class;
    }

    protected function kategorie(): string
    {
        return 'VereineModule.base';
    }

    protected function verwaltungTexte(string $name = ''): array
    {
        return [
            'nurVerwalterSehen' => Yii::t('VereineModule.base', 'Nur Verwalter dieses Vereins können das sehen.'),
            'kannVerwalten' => Yii::t('VereineModule.base', '{name} kann den Verein jetzt verwalten.', ['name' => $name]),
        ];
    }

    protected function findEintrag($id)
    {
        return $this->findVerein($id);
    }

    /** Mit dem Bild auch seinen Bildnachweis entfernen */
    protected function bildLoeschen($verein, File $file): void
    {
        $credits = $verein->getBildnachweise();
        unset($credits[$file->guid]);
        $verein->updateAttributes(['bildnachweise' => $credits ? json_encode($credits, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null]);
        $file->delete();
    }

    /** Eigenes Logo: Quellenangabe (aus der Recherche übernommenes Logo) entfällt */
    protected function logoZusatz(): array
    {
        return ['logo_quelle' => null];
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
