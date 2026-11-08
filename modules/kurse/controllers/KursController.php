<?php

namespace humhub\modules\kurse\controllers;

use humhub\modules\content\components\ContentContainerController;
use humhub\modules\kurse\components\Dienst;
use humhub\modules\kurse\models\Anmeldung;
use humhub\modules\kurse\models\Kurs;
use humhub\modules\kurse\models\KursVerein;
use humhub\modules\kurse\Module;
use humhub\modules\vereine\models\Verein;
use Yii;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;

/**
 * Kursliste, Kursseite und Verwaltung (anlegen, bearbeiten, Teilnehmerliste, Export, Rundmail, Absage).
 *
 * @property Module $module
 */
class KursController extends ContentContainerController
{
    protected function getAccessRules()
    {
        return [['login' => ['create', 'edit', 'kopieren', 'loeschen', 'veroeffentlichen', 'absagen', 'teilnehmer', 'export', 'rundmail', 'freigabe']]];
    }

    public function beforeAction($action)
    {
        Yii::$app->response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        return parent::beforeAction($action);
    }

    public function actionIndex()
    {
        if (!$this->module->kannSehen()) {
            throw new NotFoundHttpException();
        }
        $eigene = Module::eigeneVereine();
        $verwaltung = [];
        foreach ($eigene as $verein) {
            $verwaltung[] = [
                'verein' => $verein,
                'kurse' => Kurs::find()->where(['verein_id' => $verein->id])->orderBy(['beginn' => SORT_DESC])->limit(30)->all(),
            ];
        }
        return $this->render('index', [
            'kurse' => Module::sichtbareKurse(),
            'verwaltung' => $verwaltung,
            'oeffentlich' => $this->module->isOeffentlich(),
        ]);
    }

    public function actionView($id)
    {
        $kurs = $this->findKurs($id);
        $verwalten = Module::kannVerwalten($kurs->verein_id);
        if (!$verwalten && (!$this->module->kannSehen() || $kurs->status === Kurs::STATUS_ENTWURF || !Module::istFreigeschaltet($kurs->verein_id))) {
            throw new NotFoundHttpException();
        }
        return $this->render('view', ['kurs' => $kurs, 'verwalten' => $verwalten]);
    }

    public function actionDatenschutz($id)
    {
        return $this->render('datenschutz', ['kurs' => $this->findKurs($id)]);
    }

    public function actionCreate($verein)
    {
        $verein = Verein::findOne((int)$verein);
        if (!$verein || !Module::kannVerwalten($verein->id)) {
            throw new ForbiddenHttpException('Kurse dieses Vereins darfst du nicht verwalten.');
        }
        // Vorschläge aus dem letzten Kurs des Vereins übernehmen
        $letzter = Kurs::find()->where(['verein_id' => $verein->id])->orderBy(['id' => SORT_DESC])->one();
        $kurs = new Kurs([
            'verein_id' => $verein->id,
            'kontakt_email' => $letzter->kontakt_email ?? ($verein->email ?: Yii::$app->user->identity->email),
            'zahlart_bar' => $letzter->zahlart_bar ?? true,
            'zahlart_ueberweisung' => $letzter->zahlart_ueberweisung ?? true,
            'kontoinhaber' => $letzter->kontoinhaber ?? $verein->name,
            'iban' => $letzter->iban ?? null,
            'ort' => $letzter->ort ?? null,
            'bedingungen' => $letzter->bedingungen ?? null,
            'zahlungsfrist_tage' => $letzter->zahlungsfrist_tage ?? 14,
            'erinnerung_tage' => $letzter->erinnerung_tage ?? 3,
            'plaetze' => $letzter->plaetze ?? 10,
        ]);
        return $this->bearbeiten($kurs);
    }

    public function actionEdit($id)
    {
        return $this->bearbeiten($this->kursZumVerwalten($id));
    }

    private function bearbeiten(Kurs $kurs)
    {
        $neu = $kurs->isNewRecord;
        $plaetzeVorher = $kurs->plaetze;
        if ($kurs->load(Yii::$app->request->post())) {
            $kurs->verein_id = $kurs->getOldAttribute('verein_id') ?? $kurs->verein_id;
            if ($kurs->save()) {
                if (!$neu && $kurs->plaetze > $plaetzeVorher) {
                    $nach = Dienst::nachruecken($kurs);
                    if ($nach) {
                        $this->view->info(count($nach) . ' Kind(er) von der Warteliste nachgerückt und per Mail informiert.');
                    }
                }
                $this->view->saved();
                return $this->redirect($kurs->getUrl());
            }
        }
        return $this->render('edit', ['kurs' => $kurs]);
    }

    public function actionVeroeffentlichen($id)
    {
        $this->forcePostRequest();
        $kurs = $this->kursZumVerwalten($id);
        if ($kurs->status === Kurs::STATUS_ENTWURF) {
            $kurs->status = Kurs::STATUS_OFFEN;
            $kurs->save(false);
            $this->view->success('Der Kurs ist jetzt sichtbar' . ($kurs->anmeldung_ab ? ', die Anmeldung startet zur eingestellten Zeit.' : ' und die Anmeldung ist offen.'));
        }
        return $this->redirect($kurs->getUrl());
    }

    public function actionKopieren($id)
    {
        $this->forcePostRequest();
        $alt = $this->kursZumVerwalten($id);
        $kurs = new Kurs();
        $kurs->setAttributes($alt->getAttributes(null, ['id', 'status', 'absage_grund', 'erinnerung_am', 'created_at', 'created_by', 'updated_at', 'updated_by']), false);
        $kurs->status = Kurs::STATUS_ENTWURF;
        $kurs->titel = mb_substr($alt->titel . ' (Kopie)', 0, 150);
        $kurs->save(false);
        $this->view->info('Kopie angelegt. Bitte Termine und Anmeldezeitraum anpassen.');
        return $this->redirect($kurs->createUrl('/kurse/kurs/edit', ['id' => $kurs->id]));
    }

    public function actionLoeschen($id)
    {
        $this->forcePostRequest();
        $kurs = $this->kursZumVerwalten($id);
        if (Anmeldung::find()->where(['kurs_id' => $kurs->id])->andWhere(['!=', 'status', Anmeldung::STATUS_UNBESTAETIGT])->exists()) {
            $this->view->error('Für diesen Kurs gibt es schon Anmeldungen. Bitte stattdessen absagen.');
            return $this->redirect($kurs->getUrl());
        }
        $kurs->delete();
        $this->view->success('Kurs gelöscht.');
        return $this->redirect($this->contentContainer->createUrl('/kurse/kurs/index'));
    }

    public function actionAbsagen($id)
    {
        $kurs = $this->kursZumVerwalten($id);
        if (Yii::$app->request->isPost && $kurs->status !== Kurs::STATUS_ABGESAGT) {
            $n = Dienst::absagen($kurs, trim((string)Yii::$app->request->post('grund', '')));
            $this->view->success("Kurs abgesagt, $n Mail(s) verschickt.");
            return $this->redirect($kurs->getUrl());
        }
        return $this->render('absagen', ['kurs' => $kurs,
            'betroffen' => Anmeldung::find()->where(['kurs_id' => $kurs->id, 'status' => [Anmeldung::STATUS_PLATZ, Anmeldung::STATUS_WARTELISTE]])->count()]);
    }

    public function actionTeilnehmer($id, $liste = 'platz')
    {
        $kurs = $this->kursZumVerwalten($id);
        $listen = ['platz' => 'Teilnehmer', 'warteliste' => 'Warteliste', 'unbestaetigt' => 'Unbestätigt', 'storniert' => 'Abgemeldet'];
        if (!isset($listen[$liste])) {
            $liste = 'platz';
        }
        $order = $liste === Anmeldung::STATUS_PLATZ ? ['kind_nachname' => SORT_ASC, 'kind_vorname' => SORT_ASC]
            : ($liste === Anmeldung::STATUS_WARTELISTE ? ['bestaetigt_am' => SORT_ASC, 'id' => SORT_ASC] : ['created_at' => SORT_DESC]);
        $zahlen = [];
        foreach (array_keys($listen) as $s) {
            $zahlen[$s] = $kurs->zaehle($s);
        }
        return $this->render('teilnehmer', [
            'kurs' => $kurs,
            'liste' => $liste,
            'listen' => $listen,
            'zahlen' => $zahlen,
            'anmeldungen' => Anmeldung::find()->where(['kurs_id' => $kurs->id, 'status' => $liste])->orderBy($order)->all(),
            'offenBetrag' => (float)Anmeldung::find()->where(['kurs_id' => $kurs->id, 'status' => Anmeldung::STATUS_PLATZ, 'bezahlt_am' => null])->sum('betrag'),
        ]);
    }

    /** CSV für Excel (Semikolon, UTF-8 mit BOM): Teilnehmer und Warteliste */
    public function actionExport($id)
    {
        $kurs = $this->kursZumVerwalten($id);
        $zeilen = [['Status', 'Nr.', 'Vorname', 'Nachname', 'Geburtsdatum', 'Alter bei Beginn', 'Schwimmstand', 'Erziehungsberechtigte/r',
            'E-Mail', 'Telefon', 'Notfall-Telefon', 'Mitglied', 'Zahlart', 'Betrag', 'Bezahlt am', 'Notiz', 'Bestätigt am']];
        $q = Anmeldung::find()->where(['kurs_id' => $kurs->id, 'status' => [Anmeldung::STATUS_PLATZ, Anmeldung::STATUS_WARTELISTE]])
            ->orderBy([new \yii\db\Expression("status = 'warteliste'"), 'kind_nachname' => SORT_ASC]);
        foreach ($q->all() as $a) {
            $zeilen[] = [
                $a->status === Anmeldung::STATUS_PLATZ ? 'Teilnehmer' : 'Warteliste ' . $a->getWartelistenPosition(),
                $a->getNummer(), $a->kind_vorname, $a->kind_nachname,
                $a->kind_geburtsdatum ? date('d.m.Y', strtotime($a->kind_geburtsdatum)) : '', $a->getAlterText(),
                $a->schwimmstand, $a->eltern_name, $a->email, $a->telefon, $a->notfall_telefon, $a->mitglied ? 'ja' : 'nein',
                $a->zahlart === Anmeldung::ZAHLART_BAR ? 'bar' : ($a->zahlart ? 'Überweisung' : ''),
                $a->betrag !== null ? number_format((float)$a->betrag, 2, ',', '') : '',
                $a->bezahlt_am ? date('d.m.Y', strtotime($a->bezahlt_am)) : '', $a->notiz,
                $a->bestaetigt_am ? date('d.m.Y H:i', strtotime($a->bestaetigt_am)) : '',
            ];
        }
        $fh = fopen('php://temp', 'w+');
        fwrite($fh, "\xEF\xBB\xBF");
        foreach ($zeilen as $z) {
            // Formel-Injektion in Excel verhindern
            fputcsv($fh, array_map(fn($v) => preg_match('/^[=+\-@]/', (string)$v) ? "'" . $v : (string)$v, $z), ';', '"', '');
        }
        rewind($fh);
        $name = preg_replace('/[^A-Za-z0-9_-]+/', '_', $kurs->titel) . '_' . $kurs->beginn . '.csv';
        return Yii::$app->response->sendContentAsFile(stream_get_contents($fh), $name, ['mimeType' => 'text/csv']);
    }

    public function actionRundmail($id)
    {
        $kurs = $this->kursZumVerwalten($id);
        $betreff = trim((string)Yii::$app->request->post('betreff', $kurs->titel . ': '));
        $nachricht = trim((string)Yii::$app->request->post('nachricht', ''));
        $an = Yii::$app->request->post('an', 'platz');
        if (Yii::$app->request->isPost) {
            if ($betreff === '' || $nachricht === '') {
                $this->view->error('Bitte Betreff und Nachricht ausfüllen.');
            } else {
                $status = $an === 'alle' ? [Anmeldung::STATUS_PLATZ, Anmeldung::STATUS_WARTELISTE] : [Anmeldung::STATUS_PLATZ];
                $n = 0;
                foreach (Anmeldung::find()->where(['kurs_id' => $kurs->id, 'status' => $status])->andWhere(['not', ['email' => null]])->all() as $a) {
                    $n += Dienst::mailRundmail($a, $betreff, $nachricht) ? 1 : 0;
                }
                $this->view->success("$n Mail(s) verschickt.");
                return $this->redirect($kurs->createUrl('/kurse/kurs/teilnehmer', ['id' => $kurs->id]));
            }
        }
        return $this->render('rundmail', ['kurs' => $kurs, 'betreff' => $betreff, 'nachricht' => $nachricht, 'an' => $an]);
    }

    /** Admin: Verein für das Kursmodul freischalten oder sperren */
    public function actionFreigabe($verein)
    {
        $this->forcePostRequest();
        if (!Module::isAdmin()) {
            throw new ForbiddenHttpException();
        }
        $verein = Verein::findOne((int)$verein);
        if (!$verein) {
            throw new NotFoundHttpException();
        }
        $row = KursVerein::findOne($verein->id);
        if ($row) {
            $row->delete();
            $this->view->info('Kursmodul für ' . $verein->name . ' gesperrt. Bestehende Kurse bleiben gespeichert, sind aber nicht mehr sichtbar.');
        } else {
            (new KursVerein(['verein_id' => $verein->id, 'created_at' => date('Y-m-d H:i:s'), 'created_by' => Yii::$app->user->id]))->save(false);
            $this->view->success('Kursmodul für ' . $verein->name . ' freigeschaltet. Die bestätigten Verwalter des Vereins können jetzt Kurse anlegen.');
        }
        return $this->redirect($verein->getUrl());
    }

    private function findKurs($id): Kurs
    {
        $kurs = Kurs::findOne((int)$id);
        if (!$kurs) {
            throw new NotFoundHttpException('Kurs nicht gefunden.');
        }
        return $kurs;
    }

    private function kursZumVerwalten($id): Kurs
    {
        $kurs = $this->findKurs($id);
        if (!Module::kannVerwalten($kurs->verein_id)) {
            throw new ForbiddenHttpException('Diesen Kurs darfst du nicht verwalten.');
        }
        return $kurs;
    }
}
