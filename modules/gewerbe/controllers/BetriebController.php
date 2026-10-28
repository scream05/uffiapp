<?php

namespace humhub\modules\gewerbe\controllers;

use humhub\modules\content\components\ContentContainerController;
use humhub\modules\gewerbe\models\Betrieb;
use humhub\modules\gewerbe\models\BetriebBeitrag;
use humhub\modules\gewerbe\models\BetriebVerwalter;
use humhub\modules\gewerbe\Module;
use humhub\modules\gewerbe\notifications\VerwaltungAngefragt;
use humhub\modules\gewerbe\notifications\VerwaltungBestaetigt;
use humhub\modules\gewerbe\permissions\ManageGewerbe;
use humhub\modules\verzeichnis\controllers\VerwaltungTrait;
use Yii;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;

/**
 * Gewerbeverzeichnis: Liste, Betriebsseite, Eintragen/Bearbeiten, Verwaltung anfragen/bestätigen.
 *
 * @property Module $module
 */
class BetriebController extends ContentContainerController
{
    use VerwaltungTrait;

    protected function getAccessRules()
    {
        return [['login' => ['create', 'edit', 'delete', 'anfrage', 'verwalter', 'anfragen']]]; // Lesen auch für Gäste
    }

    public function actionIndex($q = '', $kategorie = '', $ortsteil = '', $offen = '', $keyword = '')
    {
        $q = $q !== '' ? $q : $keyword; // „Alle Ergebnisse anzeigen“ der Suche in der Kopfleiste
        $query = $this->filterQuery($q, $kategorie, $ortsteil);
        $betriebe = $query->all();
        if ($offen !== '') {
            $betriebe = array_values(array_filter($betriebe, fn(Betrieb $b) => ($b->getStand(true)['offen'] ?? false)));
        }
        $mine = Yii::$app->user->isGuest ? [] : Betrieb::find()->innerJoin('gewerbe_verwalter gv', 'gv.betrieb_id = gewerbe_betrieb.id')
            ->where(['gv.user_id' => Yii::$app->user->id, 'gv.status' => BetriebVerwalter::STATUS_BESTAETIGT])
            ->orderBy(['name' => SORT_ASC])->all();
        // Anzahl je Branche (nur aktive) für die Branchen-Kacheln
        $anzahl = Betrieb::find()->select(['n' => 'COUNT(*)', 'kategorie'])->where(['!=', 'status', Betrieb::STATUS_VORGESCHLAGEN])
            ->groupBy('kategorie')->indexBy('kategorie')->column();
        return $this->render('index', [
            'betriebe' => $betriebe,
            'offen' => $offen,
            'mine' => $mine,
            'anzahl' => $anzahl,
            // Betriebe mit laufendem Gutschein (Hinweis in der Liste)
            'mitGutschein' => array_flip(array_map('intval', \humhub\modules\gewerbe\models\Gutschein::sichtbar()
                ->andWhere(['status' => \humhub\modules\gewerbe\models\Gutschein::STATUS_AKTIV])->select('betrieb_id')->distinct()->column())),
            'q' => $q,
            'kategorie' => $kategorie,
            'ortsteil' => $ortsteil,
            'module' => $this->module,
        ]);
    }

    /** Karte aller Betriebe mit bekanntem Ort (gleiche Filter wie die Liste) */
    public function actionKarte($q = '', $kategorie = '', $ortsteil = '')
    {
        $punkte = [];
        foreach ($this->filterQuery($q, $kategorie, $ortsteil)->andWhere(['not', ['lat' => null]])->all() as $b) {
            $stand = $b->getStand(true);
            $punkte[] = ['lat' => (float)$b->lat, 'lng' => (float)$b->lng, 'name' => $b->name, 'kategorie' => Module::kategorieLabel($b->kategorie),
                'adresse' => (string)$b->adresse, 'url' => $b->getUrl(), 'stand' => $stand['text'] ?? '', 'offen' => $stand['offen'] ?? null];
        }
        return $this->render('karte', ['punkte' => $punkte, 'q' => $q, 'kategorie' => $kategorie, 'ortsteil' => $ortsteil,
            'module' => $this->module, 'ohneOrt' => (int)$this->filterQuery($q, $kategorie, $ortsteil)->andWhere(['lat' => null])->count()]);
    }

    private function filterQuery(string $q, string $kategorie, string $ortsteil): \yii\db\ActiveQuery
    {
        $query = Betrieb::find()->orderBy(['name' => SORT_ASC]);
        if (!Module::canManageAll()) {
            $query->andWhere(['!=', 'status', Betrieb::STATUS_VORGESCHLAGEN]);
        }
        if (trim($q) !== '') {
            $query->andWhere(Betrieb::searchCondition($q));
        }
        if (in_array($kategorie, $this->module->categories, true)) {
            $query->andWhere(['kategorie' => $kategorie]);
        }
        if (in_array($ortsteil, $this->module->ortsteile, true)) {
            $query->andWhere(['ortsteil' => $ortsteil]);
        }
        return $query;
    }

    public function actionView($id)
    {
        $betrieb = $this->findBetrieb($id);
        return $this->render('view', [
            'betrieb' => $betrieb,
            'own' => Yii::$app->user->isGuest ? null : BetriebVerwalter::findOne(['betrieb_id' => $betrieb->id, 'user_id' => Yii::$app->user->id]),
            'canManage' => $betrieb->canManage(),
            'beitraege' => BetriebBeitrag::find()->where(['betrieb_id' => $betrieb->id, 'status' => BetriebBeitrag::STATUS_VEROEFFENTLICHT,
                'auf_betriebsseite' => 1])->orderBy(['geprueft_am' => SORT_DESC, 'id' => SORT_DESC])->limit(20)->all(),
            // Eingereichte und (letzte 30 Tage) abgelehnte Beiträge sehen die Verwalter des Betriebs
            'eigene' => $betrieb->canManage() ? BetriebBeitrag::find()->with('user')->where(['betrieb_id' => $betrieb->id])
                ->andWhere(['or', ['status' => BetriebBeitrag::STATUS_EINGEREICHT],
                    ['and', ['status' => BetriebBeitrag::STATUS_ABGELEHNT], ['>=', 'geprueft_am', date('Y-m-d H:i:s', strtotime('-30 days'))]]])
                ->orderBy(['created_at' => SORT_DESC])->all() : [],
        ]);
    }

    /** Neuer Betrieb: Eintrag durch Inhaber (Freigabe durch Admin), direkt aktiv bei „Gewerbe verwalten“ */
    public function actionCreate()
    {
        $betrieb = new Betrieb(['ortsteil' => 'Uffenheim']);
        $nachricht = '';
        if ($betrieb->load(Yii::$app->request->post())) {
            $betrieb->status = Module::canManageAll() ? Betrieb::STATUS_AKTIV : Betrieb::STATUS_VORGESCHLAGEN;
            $nachricht = trim((string)Yii::$app->request->post('nachricht', ''));
            if ($betrieb->save()) {
                $this->saveImages($betrieb);
                if (!Module::canManageAll()) {
                    $this->verwaltungAnfragen($betrieb, $nachricht);
                    $this->view->success(Yii::t('GewerbeModule.base', 'Danke! Dein Eintrag wird kurz geprüft. Danach erscheint er im Verzeichnis und du kannst ihn pflegen.'));
                }
                return $this->redirect($betrieb->getUrl());
            }
        }
        return $this->render('edit', ['betrieb' => $betrieb, 'isNew' => true, 'nachricht' => $nachricht, 'module' => $this->module]);
    }

    public function actionEdit($id)
    {
        $betrieb = $this->findBetrieb($id);
        if (!$betrieb->canManage()) {
            throw new ForbiddenHttpException(Yii::t('GewerbeModule.base', 'Nur Verwalter dieses Betriebs können die Seite bearbeiten.'));
        }
        if ($betrieb->load(Yii::$app->request->post())) {
            // Umbenennen nur durch Admins (der Name ist das Erkennungsmerkmal im Verzeichnis)
            if (!Module::canManageAll()) {
                $betrieb->name = $betrieb->getOldAttribute('name');
            }
            // Startlisten-Eintrag: mit dem ersten Speichern durch den Betrieb gilt er als gepflegt
            if ($betrieb->status === Betrieb::STATUS_OFFEN) {
                $betrieb->status = Betrieb::STATUS_AKTIV;
                $betrieb->quelle = null;
                $betrieb->recherchiert_am = null;
            }
            if ($betrieb->save()) {
                $this->saveImages($betrieb);
                $this->view->saved();
                return $this->redirect($betrieb->getUrl());
            }
        }
        return $this->render('edit', ['betrieb' => $betrieb, 'isNew' => false, 'nachricht' => '', 'module' => $this->module]);
    }

    public function actionDelete($id)
    {
        $this->forcePostRequest();
        if (!Module::canManageAll()) {
            throw new ForbiddenHttpException();
        }
        $betrieb = $this->findBetrieb($id);
        $betrieb->delete();
        $this->view->success(Yii::t('GewerbeModule.base', '„{name}“ wurde aus dem Verzeichnis entfernt.', ['name' => $betrieb->name]));
        return $this->redirect($this->contentContainer->createUrl('/gewerbe/betrieb/index'));
    }

    // Verwaltung anfragen/bestätigen, offene Anfragen, Logo und Bilder: VerwaltungTrait (modules/verzeichnis)

    protected function eintragName(): string
    {
        return 'betrieb';
    }

    protected function eintragKlasse(): string
    {
        return Betrieb::class;
    }

    protected function verwalterKlasse(): string
    {
        return BetriebVerwalter::class;
    }

    protected function verwaltenRecht(): string
    {
        return ManageGewerbe::class;
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
        return 'GewerbeModule.base';
    }

    protected function verwaltungTexte(string $name = ''): array
    {
        return [
            'nurVerwalterSehen' => Yii::t('GewerbeModule.base', 'Nur Verwalter dieses Betriebs können das sehen.'),
            'kannVerwalten' => Yii::t('GewerbeModule.base', '{name} kann den Betrieb jetzt verwalten.', ['name' => $name]),
        ];
    }

    protected function findEintrag($id)
    {
        return $this->findBetrieb($id);
    }

    private function findBetrieb($id): Betrieb
    {
        $betrieb = Betrieb::findOne((int)$id);
        if ($betrieb === null || ($betrieb->status === Betrieb::STATUS_VORGESCHLAGEN && !$betrieb->canManage()
                && !BetriebVerwalter::find()->where(['betrieb_id' => $betrieb->id, 'user_id' => Yii::$app->user->id])->exists())) {
            throw new NotFoundHttpException(Yii::t('GewerbeModule.base', 'Betrieb nicht gefunden.'));
        }
        return $betrieb;
    }
}
