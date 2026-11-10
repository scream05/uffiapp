<?php

namespace humhub\modules\kurse\controllers;

use humhub\modules\content\components\ContentContainerController;
use humhub\modules\kurse\components\Dienst;
use humhub\modules\kurse\models\Anmeldung;
use humhub\modules\kurse\models\Kurs;
use humhub\modules\kurse\Module;
use Yii;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;

/**
 * Anmeldung durch Eltern (ohne Konto, Bestätigung per Mail, persönlicher Link mit Token)
 * und Bearbeitung einzelner Anmeldungen durch Kursverwalter.
 *
 * Bestätigen und Abmelden laufen per POST hinter einer Seite mit Knopf: Virenscanner und Vorschaufunktionen
 * von Mailprogrammen rufen Links aus Mails oft automatisch auf.
 *
 * @property Module $module
 */
class AnmeldungController extends ContentContainerController
{
    protected function getAccessRules()
    {
        return [['login' => ['hand', 'bezahlt', 'platz', 'abmelden', 'notiz']]];
    }

    public function beforeAction($action)
    {
        Yii::$app->response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        Yii::$app->response->headers->set('Referrer-Policy', 'no-referrer'); // Token nicht weitergeben
        return parent::beforeAction($action);
    }

    public function actionCreate($kurs)
    {
        $kurs = Kurs::findOne((int)$kurs);
        if (!$kurs || $kurs->status === Kurs::STATUS_ENTWURF || !Module::istFreigeschaltet($kurs->verein_id)
            || (!$this->module->kannSehen() && !Module::kannVerwalten($kurs->verein_id))) {
            throw new NotFoundHttpException('Kurs nicht gefunden.');
        }
        if (($grund = $kurs->anmeldungGesperrt()) !== null) {
            $this->view->info($grund);
            return $this->redirect($kurs->getUrl());
        }
        $a = new Anmeldung(['kurs_id' => $kurs->id, 'scenario' => Anmeldung::SCENARIO_ELTERN]);
        $a->populateRelation('kurs', $kurs);
        if (!Yii::$app->user->isGuest && !Yii::$app->request->isPost) {
            $u = Yii::$app->user->identity;
            $a->eltern_name = $u->displayName;
            $a->email = $u->email;
            $a->telefon = $u->profile->mobile ?? null;
        }
        if ($a->load(Yii::$app->request->post())) {
            if (Dienst::formularAnmelden($a, $hinweis)) {
                return $this->render('gesendet', ['a' => $a]);
            }
            if ($hinweis) {
                $this->view->error($hinweis);
            }
        }
        return $this->render('create', ['a' => $a, 'kurs' => $kurs]);
    }

    public function actionBestaetigen($t)
    {
        $a = $this->findToken($t);
        if (Yii::$app->request->isPost && $a->status === Anmeldung::STATUS_UNBESTAETIGT) {
            Dienst::bestaetigen($a);
            $a->refresh();
            return $this->redirect($a->getStatusUrl(false));
        }
        if ($a->status !== Anmeldung::STATUS_UNBESTAETIGT) {
            return $this->redirect($a->getStatusUrl(false));
        }
        return $this->render('bestaetigen', ['a' => $a]);
    }

    public function actionStatus($t)
    {
        $a = $this->findToken($t);
        if (Yii::$app->request->isPost && Yii::$app->request->post('aktion') === 'abmelden') {
            Dienst::stornieren($a, 'eltern');
            $this->view->success('Abmeldung gespeichert.');
            return $this->redirect($a->getStatusUrl(false));
        }
        return $this->render('status', ['a' => $a]);
    }

    // ------------------------------------------------------------ Verwaltung

    /** Anmeldung von Hand eintragen (Papierformular, Telefon) */
    public function actionHand($kurs)
    {
        $kurs = Kurs::findOne((int)$kurs);
        if (!$kurs || !Module::kannVerwalten($kurs->verein_id)) {
            throw new ForbiddenHttpException();
        }
        $a = new Anmeldung(['kurs_id' => $kurs->id, 'scenario' => Anmeldung::SCENARIO_HAND]);
        $a->populateRelation('kurs', $kurs);
        if ($a->load(Yii::$app->request->post()) && Dienst::vonHand($a, (bool)Yii::$app->request->post('mail'))) {
            $this->view->success($a->getKindName() . ': ' . $a->getStatusText() . '.');
            return $this->redirect($kurs->createUrl('/kurse/kurs/teilnehmer', ['id' => $kurs->id, 'liste' => $a->status]));
        }
        return $this->render('hand', ['a' => $a, 'kurs' => $kurs]);
    }

    public function actionBezahlt($id)
    {
        $a = $this->zumVerwalten($id);
        $a->bezahlt_am = $a->bezahlt_am ? null : (Yii::$app->request->post('datum') ?: date('Y-m-d'));
        $a->save(false);
        return $this->zurueck($a);
    }

    public function actionPlatz($id)
    {
        $a = $this->zumVerwalten($id);
        Dienst::platzGeben($a, (bool)Yii::$app->request->post('mail', 1));
        $this->view->success($a->getKindName() . ' hat jetzt einen Platz' . ($a->email ? ' und wurde per Mail informiert.' : '.'));
        return $this->zurueck($a, Anmeldung::STATUS_PLATZ);
    }

    public function actionAbmelden($id)
    {
        $a = $this->zumVerwalten($id);
        $status = $a->status;
        Dienst::stornieren($a, 'verein', (bool)Yii::$app->request->post('mail', 1));
        $this->view->success($a->getKindName() . ' abgemeldet.');
        return $this->zurueck($a, $status);
    }

    public function actionNotiz($id)
    {
        $a = $this->zumVerwalten($id);
        $a->notiz = mb_substr(trim((string)Yii::$app->request->post('notiz', '')), 0, 1000) ?: null;
        $a->save(false);
        return $this->zurueck($a);
    }

    private function zurueck(Anmeldung $a, ?string $liste = null)
    {
        return $this->redirect($a->kurs->createUrl('/kurse/kurs/teilnehmer', ['id' => $a->kurs_id, 'liste' => $liste ?? $a->status]) . '#a' . $a->id);
    }

    private function zumVerwalten($id): Anmeldung
    {
        $this->forcePostRequest();
        $a = Anmeldung::findOne((int)$id);
        if (!$a || !Module::kannVerwalten($a->kurs->verein_id)) {
            throw new ForbiddenHttpException();
        }
        return $a;
    }

    private function findToken($t): Anmeldung
    {
        $a = is_string($t) && strlen($t) === 40 ? Anmeldung::findOne(['token' => $t]) : null;
        if (!$a || $a->anonymisiert_am) {
            throw new NotFoundHttpException('Diese Anmeldung gibt es nicht (mehr). Nicht bestätigte Anmeldungen werden nach '
                . $this->module->unbestaetigtStunden . ' Stunden gelöscht – dann bitte einfach neu anmelden.');
        }
        return $a;
    }
}
