<?php

namespace humhub\modules\gewerbe\controllers;

use humhub\modules\content\components\ContentContainerController;
use humhub\modules\gewerbe\components\GutscheinDienst;
use humhub\modules\gewerbe\models\Betrieb;
use humhub\modules\gewerbe\models\Einloesung;
use humhub\modules\gewerbe\models\Gutschein;
use humhub\modules\gewerbe\Module;
use Yii;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;

/**
 * Rabatt-Gutscheine: Liste und Gutscheinseite (auch Gäste), Einlösen im Laden über den Theken-QR oder die Nummer
 * vom Aufsteller, Bestätigungsbildschirm zum Vorzeigen; Verwalter legen Gutscheine an und sehen die Einlösungen.
 *
 * @property Module $module
 */
class GutscheinController extends ContentContainerController
{
    protected function getAccessRules()
    {
        return [['login' => ['theke', 'einloesen', 'bestaetigung', 'create', 'edit', 'status', 'verwaltung', 'stornieren',
            'theke-erneuern', 'aufsteller']]];
    }

    public function actionIndex()
    {
        $gutscheine = Gutschein::sichtbar()->innerJoinWith('betrieb b')->andWhere(['!=', 'b.status', Betrieb::STATUS_VORGESCHLAGEN])
            ->orderBy(['gewerbe_gutschein.created_at' => SORT_DESC])->all();
        // Einlösbare zuerst, pausierte/aufgebrauchte/kommende danach
        usort($gutscheine, fn(Gutschein $a, Gutschein $b) => (int)!$this->offen($a) <=> (int)!$this->offen($b));
        return $this->render('index', ['gutscheine' => $gutscheine, 'mine' => $this->meineBetriebe()]);
    }

    public function actionView($id)
    {
        $g = $this->finde($id);
        $userId = Yii::$app->user->isGuest ? null : Yii::$app->user->id;
        if ($userId === null) {
            Yii::$app->user->setReturnUrl($g->getUrl()); // nach „Anmelden“ zurück zum Gutschein
        }
        return $this->render('view', [
            'g' => $g,
            'grund' => GutscheinDienst::grund($g, $userId),
            'canManage' => $g->betrieb->canManage(),
            'letzte' => $userId ? $this->letzteLive($g, $userId) : null,
        ]);
    }

    /** Ziel des Theken-QR: alle Gutscheine des Betriebs, die die Person jetzt einlösen kann */
    public function actionTheke($t = '')
    {
        $betrieb = GutscheinDienst::betriebZuToken((string)$t);
        if ($betrieb === null) {
            return $this->render('theke', ['betrieb' => null, 'gutscheine' => [], 't' => '']);
        }
        $gutscheine = Gutschein::sichtbar()->andWhere(['betrieb_id' => $betrieb->id])->orderBy(['created_at' => SORT_DESC])->all();
        $userId = Yii::$app->user->id;
        usort($gutscheine, fn(Gutschein $a, Gutschein $b) => (int)(GutscheinDienst::grund($a, $userId) !== null) <=> (int)(GutscheinDienst::grund($b, $userId) !== null));
        return $this->render('theke', ['betrieb' => $betrieb, 'gutscheine' => $gutscheine, 't' => $t]);
    }

    /** Einlösen: mit Token des Theken-QR (t) oder der Nummer vom Aufsteller */
    public function actionEinloesen($id)
    {
        $this->forcePostRequest();
        $g = $this->finde($id);
        $request = Yii::$app->request;
        $token = (string)$request->post('t', '');
        if ($token !== '') {
            if (GutscheinDienst::betriebZuToken($token)?->id !== $g->betrieb_id) {
                $this->view->error(Yii::t('GewerbeModule.base', 'Dieser QR-Code gehört nicht zu diesem Betrieb oder ist nicht mehr gültig. Bitte scanne den Aufsteller an der Kasse.'));
                return $this->redirect($g->getUrl());
            }
        } elseif (($fehler = GutscheinDienst::nummerPruefen($g->betrieb, (string)$request->post('nummer', ''), Yii::$app->user->id)) !== null) {
            $this->view->error($fehler);
            return $this->redirect($g->getUrl());
        }
        $e = GutscheinDienst::einloesen($g, Yii::$app->user->id);
        if (is_string($e)) {
            $this->view->error($e);
            return $this->redirect($g->getUrl());
        }
        return $this->redirect($this->contentContainer->createUrl('/gewerbe/gutschein/bestaetigung', ['id' => $e->id]));
    }

    /** Bestätigungsbildschirm zum Vorzeigen (nur für die einlösende Person) */
    public function actionBestaetigung($id)
    {
        $e = Einloesung::findOne((int)$id);
        if ($e === null || $e->user_id !== Yii::$app->user->id) {
            throw new NotFoundHttpException(Yii::t('GewerbeModule.base', 'Einlösung nicht gefunden.'));
        }
        return $this->render('bestaetigung', [
            'e' => $e,
            'live' => $e->storniert_am === null && $e->alter() < 60 * $this->module->bestaetigungMinuten,
            'rest' => max(0, 60 * $this->module->bestaetigungMinuten - $e->alter()),
        ]);
    }

    public function actionCreate($betrieb)
    {
        $b = $this->betriebVerwalten($betrieb);
        $g = new Gutschein(['betrieb_id' => $b->id, 'status' => Gutschein::STATUS_AKTIV, 'pro_person' => 'einmal',
            'gueltig_ab' => date('Y-m-d'), 'gueltig_bis' => date('Y-m-d', strtotime('+1 month'))]);
        $g->scenario = 'formular';
        if ($g->load(Yii::$app->request->post()) && $g->save()) {
            GutscheinDienst::theke($b);
            GutscheinDienst::neu($g);
            $this->view->success(Yii::t('GewerbeModule.base', 'Dein Gutschein ist veröffentlicht. Druck jetzt den Theken-Aufsteller aus, damit deine Kunden ihn einlösen können.'));
            return $this->redirect($this->contentContainer->createUrl('/gewerbe/gutschein/verwaltung', ['betrieb' => $b->id]));
        }
        return $this->render('edit', ['g' => $g, 'betrieb' => $b, 'isNew' => true]);
    }

    public function actionEdit($id)
    {
        $g = $this->finde($id);
        $this->betriebVerwalten($g->betrieb_id);
        if ($g->load(Yii::$app->request->post()) && $g->save()) {
            $this->view->saved();
            return $this->redirect($this->contentContainer->createUrl('/gewerbe/gutschein/verwaltung', ['betrieb' => $g->betrieb_id]));
        }
        return $this->render('edit', ['g' => $g, 'betrieb' => $g->betrieb, 'isNew' => false]);
    }

    /** Pausieren, wieder starten, beenden oder löschen */
    public function actionStatus($id)
    {
        $this->forcePostRequest();
        $g = $this->finde($id);
        $this->betriebVerwalten($g->betrieb_id);
        $zurueck = $this->contentContainer->createUrl('/gewerbe/gutschein/verwaltung', ['betrieb' => $g->betrieb_id]);
        switch (Yii::$app->request->post('aktion')) {
            case 'pausieren':
                GutscheinDienst::pausieren($g, null);
                $this->view->success(Yii::t('GewerbeModule.base', 'Pausiert. Der Gutschein bleibt sichtbar, lässt sich aber nicht einlösen.'));
                break;
            case 'starten':
                if ($g->istAbgelaufen()) {
                    $this->view->error(Yii::t('GewerbeModule.base', 'Der Zeitraum ist vorbei. Bitte zuerst „Gültig bis“ ändern.'));
                    break;
                }
                $g->updateAttributes(['status' => Gutschein::STATUS_AKTIV, 'pause_grund' => null]);
                $this->view->success(Yii::t('GewerbeModule.base', 'Der Gutschein lässt sich wieder einlösen.'));
                break;
            case 'beenden':
                $g->updateAttributes(['status' => Gutschein::STATUS_BEENDET, 'pause_grund' => null]);
                $this->view->success(Yii::t('GewerbeModule.base', 'Beendet. Der Gutschein ist nicht mehr zu sehen.'));
                break;
            case 'loeschen':
                $g->delete();
                $this->view->success(Yii::t('GewerbeModule.base', 'Gutschein gelöscht.'));
                break;
            default:
                throw new BadRequestHttpException(Yii::t('GewerbeModule.base', 'Unbekannte Aktion.'));
        }
        return $this->redirect($zurueck);
    }

    /** Gutscheine eines Betriebs, Einlösungen (ohne Personen), Theken-QR */
    public function actionVerwaltung($betrieb)
    {
        $b = GutscheinDienst::theke($this->betriebVerwalten($betrieb));
        $gutscheine = Gutschein::find()->where(['betrieb_id' => $b->id])
            ->orderBy([new \yii\db\Expression("FIELD(status, 'aktiv', 'pausiert', 'beendet')"), 'created_at' => SORT_DESC])->all();
        $heute = Einloesung::find()->select(['n' => 'COUNT(*)', 'gutschein_id'])
            ->where(['betrieb_id' => $b->id, 'storniert_am' => null])->andWhere(['>=', 'created_at', date('Y-m-d 00:00:00')])
            ->groupBy('gutschein_id')->indexBy('gutschein_id')->column();
        return $this->render('verwaltung', [
            'betrieb' => $b,
            'gutscheine' => $gutscheine,
            'heute' => $heute,
            'einloesungen' => Einloesung::find()->with('gutschein')->where(['betrieb_id' => $b->id])
                ->andWhere(['>=', 'created_at', date('Y-m-d H:i:s', strtotime('-30 days'))])
                ->orderBy(['created_at' => SORT_DESC])->limit(100)->all(),
        ]);
    }

    public function actionStornieren($id)
    {
        $this->forcePostRequest();
        $e = Einloesung::findOne((int)$id);
        if ($e === null) {
            throw new NotFoundHttpException();
        }
        $this->betriebVerwalten($e->betrieb_id);
        GutscheinDienst::stornieren($e)
            ? $this->view->success(Yii::t('GewerbeModule.base', 'Einlösung {code} zurückgenommen. Die Person kann den Gutschein erneut nutzen.', ['code' => $e->code]))
            : $this->view->error(Yii::t('GewerbeModule.base', 'Zurücknehmen geht nur in den ersten {minuten} Minuten.', ['minuten' => $this->module->stornoMinuten]));
        return $this->redirect($this->contentContainer->createUrl('/gewerbe/gutschein/verwaltung', ['betrieb' => $e->betrieb_id]));
    }

    public function actionThekeErneuern($betrieb)
    {
        $this->forcePostRequest();
        $b = $this->betriebVerwalten($betrieb);
        GutscheinDienst::thekeErneuern($b);
        $this->view->success(Yii::t('GewerbeModule.base', 'Neuer Theken-QR und neue Nummer. Bitte den neuen Aufsteller ausdrucken; der alte gilt nicht mehr.'));
        return $this->redirect($this->contentContainer->createUrl('/gewerbe/gutschein/verwaltung', ['betrieb' => $b->id]));
    }

    /** Druckseite für die Kasse (A6-Aufsteller bzw. Aufkleber) */
    public function actionAufsteller($betrieb)
    {
        $b = GutscheinDienst::theke($this->betriebVerwalten($betrieb));
        return $this->renderPartial('aufsteller', ['betrieb' => $b, 'url' => GutscheinDienst::thekeUrl($b)]);
    }

    /**
     * QR-Code als SVG: art=werbe (Link zum Gutschein, für Flyer und Schaufenster; öffentlich) oder art=theke (nur Verwalter).
     * Mit download=1 als Datei.
     */
    public function actionQr($id, $art = 'werbe', $download = 0)
    {
        if ($art === 'theke') {
            $b = GutscheinDienst::theke($this->betriebVerwalten($id));
            $text = GutscheinDienst::thekeUrl($b);
            $name = 'theken-qr-' . $b->id;
        } else {
            $g = $this->finde($id);
            $text = $g->getUrl(true);
            $name = 'gutschein-' . $g->id;
        }
        $response = Yii::$app->response;
        $response->format = \yii\web\Response::FORMAT_RAW;
        $response->headers->set('Content-Type', 'image/svg+xml');
        $response->headers->set('Content-Security-Policy', "default-src 'none'; style-src 'unsafe-inline'");
        $response->headers->set('Cache-Control', 'private, max-age=300');
        if ($download) {
            $response->headers->set('Content-Disposition', "attachment; filename=\"$name.svg\"");
        }
        return GutscheinDienst::qrSvg($text, 600);
    }

    private function offen(Gutschein $g): bool
    {
        return GutscheinDienst::grundAllgemein($g) === null;
    }

    /** Einlösung der Person in den letzten Minuten (Bestätigung noch einmal zeigen) */
    private function letzteLive(Gutschein $g, int $userId): ?Einloesung
    {
        return Einloesung::find()->where(['gutschein_id' => $g->id, 'user_id' => $userId, 'storniert_am' => null])
            ->andWhere(['>=', 'created_at', date('Y-m-d H:i:s', time() - 60 * $this->module->bestaetigungMinuten)])
            ->orderBy(['created_at' => SORT_DESC])->one();
    }

    /** @return Betrieb[] */
    private function meineBetriebe(): array
    {
        if (Yii::$app->user->isGuest) {
            return [];
        }
        return Betrieb::find()->innerJoin('gewerbe_verwalter gv', 'gv.betrieb_id = gewerbe_betrieb.id')
            ->where(['gv.user_id' => Yii::$app->user->id, 'gv.status' => 'bestaetigt'])
            ->andWhere(['!=', 'gewerbe_betrieb.status', Betrieb::STATUS_VORGESCHLAGEN])->orderBy(['name' => SORT_ASC])->all();
    }

    private function betriebVerwalten($id): Betrieb
    {
        $b = Betrieb::findOne((int)$id);
        if ($b === null || $b->status === Betrieb::STATUS_VORGESCHLAGEN) {
            throw new NotFoundHttpException(Yii::t('GewerbeModule.base', 'Betrieb nicht gefunden.'));
        }
        if (!$b->canManage()) {
            throw new ForbiddenHttpException(Yii::t('GewerbeModule.base', 'Nur Verwalter dieses Betriebs können Gutscheine verwalten.'));
        }
        return $b;
    }

    private function finde($id): Gutschein
    {
        $g = Gutschein::findOne((int)$id);
        if ($g === null || $g->betrieb->status === Betrieb::STATUS_VORGESCHLAGEN) {
            throw new NotFoundHttpException(Yii::t('GewerbeModule.base', 'Gutschein nicht gefunden.'));
        }
        return $g;
    }
}
