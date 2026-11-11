<?php

namespace humhub\modules\kurse\controllers;

use humhub\modules\kurse\components\Dienst;
use humhub\modules\kurse\models\Anmeldung;
use humhub\modules\kurse\models\Kurs;
use humhub\modules\kurse\models\KursVerein;
use humhub\modules\kurse\Module;
use humhub\modules\vereine\models\Verein;
use Yii;
use yii\web\Controller;
use yii\web\NotFoundHttpException;

/**
 * Eigene Kursseite eines Vereins unter /kurse/<slug> (z. B. /kurse/sbu-schwimmen): Vorstellung, Kurse, Anmeldung,
 * Bestätigen, Status/Abmelden und Datenschutzhinweise – ohne Menüs und Skripte der Plattform, im Design des Vereins.
 * Gedacht zum Verlinken von der eigenen Homepage des Vereins.
 *
 * Bewusst ein schlanker yii-Controller (kein HumHub-Layout, keine HumHub-Skripte). Die Logik (Platzvergabe, Mails,
 * Warteliste) steckt in Dienst und den Modellen und ist dieselbe wie auf den Kursseiten im Space.
 * Verwalten (Kurse anlegen, Teilnehmerliste …) läuft weiter angemeldet im Space „Vereine & Ehrenamt“.
 *
 * Sichtbarkeit: Solange kurs_verein.seite_aktiv = 0 ist, sehen die Seite nur Admins und Kursverwalter des Vereins
 * (Vorschau). Bestätigen- und Status-Links aus den Mails gehen immer.
 *
 * @property Module $module
 */
class SeiteController extends Controller
{
    public $layout = 'seite';

    public KursVerein $seite;
    public Verein $verein;
    /** Vorschau (Seite noch nicht freigegeben) – wird im Layout angezeigt */
    public bool $vorschau = false;

    public function beforeAction($action)
    {
        $h = Yii::$app->response->headers;
        $h->set('X-Robots-Tag', 'noindex, nofollow');
        $h->set('Referrer-Policy', 'no-referrer'); // Token aus den Mail-Links nicht weitergeben
        $h->set('Cache-Control', 'no-store');
        return parent::beforeAction($action);
    }

    /** Startseite: Titelbereich, Kurse, Vorstellung, Team, Kontakt */
    public function actionIndex($slug)
    {
        $this->ladeSeite($slug);
        return $this->render('index', [
            'kurse' => Module::sichtbareKurse($this->verein->id),
        ]);
    }

    public function actionKurs($slug, $id)
    {
        $kurs = $this->ladeKurs($slug, $id);
        return $this->render('kurs', ['kurs' => $kurs]);
    }

    public function actionAnmelden($slug, $id)
    {
        $kurs = $this->ladeKurs($slug, $id);
        if ($kurs->anmeldungGesperrt() !== null) {
            // den Grund zeigt die Kursseite
            return $this->redirect($kurs->elternUrl('kurs'));
        }
        $a = new Anmeldung(['kurs_id' => $kurs->id, 'scenario' => Anmeldung::SCENARIO_ELTERN]);
        $a->populateRelation('kurs', $kurs);
        if (!Yii::$app->user->isGuest && !Yii::$app->request->isPost) {
            $u = Yii::$app->user->identity;
            $a->eltern_name = $u->displayName;
            $a->email = $u->email;
            $a->telefon = $u->profile->mobile ?? null;
        }
        $hinweis = null;
        if ($a->load(Yii::$app->request->post())) {
            if (Dienst::formularAnmelden($a, $hinweis)) {
                return $this->render('gesendet', ['a' => $a]);
            }
        }
        return $this->render('anmelden', ['a' => $a, 'kurs' => $kurs, 'hinweis' => $hinweis]);
    }

    /** Datenschutzhinweise der ganzen Kursseite (Fußzeile) */
    public function actionDatenschutzseite($slug)
    {
        $this->ladeSeite($slug);
        return $this->render('datenschutzseite');
    }

    public function actionDatenschutz($slug, $id)
    {
        $this->ladeSeite($slug, false);
        $kurs = Kurs::findOne(['id' => (int)$id, 'verein_id' => $this->verein->id]);
        if (!$kurs) {
            throw new NotFoundHttpException('Kurs nicht gefunden.');
        }
        return $this->render('datenschutz', ['kurs' => $kurs]);
    }

    /** Link aus der Bestätigungsmail: erst Seite mit Knopf, dann POST (Link-Scanner der Mailprogramme) */
    public function actionBestaetigen($slug, $t)
    {
        $a = $this->ladeToken($slug, $t);
        if ($a->status !== Anmeldung::STATUS_UNBESTAETIGT) {
            return $this->redirect($a->getStatusUrl(false));
        }
        if (Yii::$app->request->isPost) {
            Dienst::bestaetigen($a);
            return $this->redirect($a->getStatusUrl(false));
        }
        return $this->render('bestaetigen', ['a' => $a]);
    }

    /** Persönliche Seite der Anmeldung (Stand, Zahlung, Abmelden) */
    public function actionStatus($slug, $t)
    {
        $a = $this->ladeToken($slug, $t);
        if (Yii::$app->request->isPost && Yii::$app->request->post('aktion') === 'abmelden'
            && in_array($a->status, [Anmeldung::STATUS_PLATZ, Anmeldung::STATUS_WARTELISTE], true)) {
            Dienst::stornieren($a, 'eltern');
            Yii::$app->session->setFlash('kurse-ok', 'Abmeldung gespeichert. Du bekommst gleich eine Bestätigung per Mail.');
            return $this->redirect($a->getStatusUrl(false));
        }
        return $this->render('status', ['a' => $a]);
    }

    // ------------------------------------------------------------------------------------------------

    /**
     * @param bool $nurFreigegeben false = auch ohne Freigabe zeigen (Datenschutz, Mail-Links)
     */
    private function ladeSeite($slug, bool $nurFreigegeben = true): void
    {
        $seite = is_string($slug) && KursVerein::gueltigerSlug($slug) ? KursVerein::findOne(['slug' => $slug]) : null;
        $verein = $seite ? Verein::findOne($seite->verein_id) : null;
        if (!$seite || !$verein) {
            throw new NotFoundHttpException('Diese Seite gibt es nicht.');
        }
        if (!$seite->seite_aktiv) {
            // Vorschau: Kursverwalter/Admins oder wer den Vorschau-Link hat (gilt dann für die Sitzung)
            $key = 'kurse-vorschau-' . $seite->verein_id;
            $v = Yii::$app->request->get('vorschau');
            if (is_string($v) && hash_equals($seite->vorschauSchluessel(), $v)) {
                Yii::$app->session->set($key, $seite->vorschauSchluessel());
            }
            $hatLink = Yii::$app->session->get($key) === $seite->vorschauSchluessel();
            $this->vorschau = $hatLink || Module::kannVerwalten($verein->id);
            if ($nurFreigegeben && !$this->vorschau) {
                throw new NotFoundHttpException('Diese Seite gibt es (noch) nicht.');
            }
        }
        $this->seite = $seite;
        $this->verein = $verein;
        $this->view->title = $verein->name;
    }

    private function ladeKurs($slug, $id): Kurs
    {
        $this->ladeSeite($slug);
        $kurs = Kurs::findOne(['id' => (int)$id, 'verein_id' => $this->verein->id]);
        if (!$kurs || ($kurs->status === Kurs::STATUS_ENTWURF && !Module::kannVerwalten($this->verein->id))) {
            throw new NotFoundHttpException('Diesen Kurs gibt es nicht (mehr).');
        }
        $this->view->title = $kurs->titel . ' – ' . $this->verein->name;
        return $kurs;
    }

    private function ladeToken($slug, $t): Anmeldung
    {
        $this->ladeSeite($slug, false);
        $a = is_string($t) && strlen($t) === 40 ? Anmeldung::findOne(['token' => $t]) : null;
        if (!$a || $a->anonymisiert_am || (int)$a->kurs->verein_id !== (int)$this->verein->id) {
            throw new NotFoundHttpException('Diese Anmeldung gibt es nicht (mehr). Nicht bestätigte Anmeldungen werden nach '
                . $this->module->unbestaetigtStunden . ' Stunden gelöscht – dann bitte einfach neu anmelden.');
        }
        $this->view->title = 'Anmeldung ' . $a->getNummer() . ' – ' . $this->verein->name;
        return $a;
    }
}
