<?php

namespace humhub\modules\stadtrat\controllers;

use humhub\modules\content\components\ContentContainerController;
use humhub\modules\space\models\Space;
use humhub\modules\stadtrat\components\Erklaert;
use humhub\modules\stadtrat\jobs\AudioJob;
use humhub\modules\stadtrat\jobs\EntwurfJob;
use humhub\modules\stadtrat\models\Sitzung;
use Yii;
use yii\web\NotFoundHttpException;
use yii\web\UploadedFile;

/**
 * „Stadtrat kurz erklärt“ schreiben und freigeben (nur Admins, Module::darfBearbeiten), im Space „Rathaus Uffenheim“.
 * Entwurf und Audio laufen in der Warteschlange; die Seite lädt sich währenddessen neu.
 */
class KurzfassungController extends ContentContainerController
{
    public $requireContainer = false;

    /** Hängt ein Auftrag länger (Warteschlange abgestürzt), darf man neu starten */
    private const ARBEIT_MINUTEN = 15;

    protected function getAccessRules()
    {
        return [];
    }

    public function beforeAction($action)
    {
        if (!parent::beforeAction($action)) {
            return false;
        }
        if (!$this->module->darfBearbeiten(Yii::$app->user->getIdentity())
            || ($this->contentContainer !== null && $this->contentContainer->url !== $this->module->spaceUrl)) {
            throw new NotFoundHttpException();
        }
        // Nur Aufrufe umleiten – Formulare (POST) gehen ohne Space-Pfad direkt durch, sonst gingen ihre Daten verloren
        if ($this->contentContainer === null && Yii::$app->request->isGet && ($space = Space::findOne(['url' => $this->module->spaceUrl])) !== null) {
            $this->redirect($space->createUrl('/stadtrat/kurzfassung/' . $action->id, Yii::$app->request->get()));
            return false;
        }
        return true;
    }

    public function actionIndex()
    {
        $sitzungen = Sitzung::vergangene()->limit(30)->all();
        $fassungen = \humhub\modules\stadtrat\models\Kurzfassung::find()->where(['sitzung_id' => array_map(fn($s) => $s->id, $sitzungen) ?: [0]])->indexBy('sitzung_id')->all();
        $this->view->setPageTitle(Yii::t('StadtratModule.base', 'Stadtrat kurz erklärt'));
        return $this->render('index', ['sitzungen' => $sitzungen, 'fassungen' => $fassungen]);
    }

    public function actionBearbeiten($id)
    {
        $s = Sitzung::findOne((int)$id) ?? throw new NotFoundHttpException();
        $k = Erklaert::fassung($s->id);
        $laeuft = $k->arbeit !== null && strtotime($k->geaendert_am) > time() - self::ARBEIT_MINUTEN * 60;
        $req = Yii::$app->request;
        if ($req->isPost && !$laeuft) {
            $tun = (string)$req->post('tun');
            $user = Yii::$app->user->getIdentity();
            if ($tun === 'hochladen') {
                $datei = UploadedFile::getInstanceByName('pdf');
                if ($datei === null || $datei->error !== UPLOAD_ERR_OK || strtolower($datei->extension) !== 'pdf'
                    || !str_starts_with((string)file_get_contents($datei->tempName, false, null, 0, 5), '%PDF')) {
                    $this->view->error(Yii::t('StadtratModule.base', 'Bitte eine PDF-Datei wählen.'));
                    return $this->redirect(['bearbeiten', 'id' => $s->id]);
                }
                Erklaert::speichernPdf($s->id, $datei->tempName);
                $tun = 'entwurf';
            }
            if (in_array($tun, ['speichern', 'audio', 'freigeben'], true) && $k->kurz !== null) {
                $vorher = $k->vorlese;
                $k->setAttributes(['kurz' => trim((string)$req->post('kurz')), 'lang' => trim((string)$req->post('lang')),
                    'vorlese' => trim((string)$req->post('vorlese'))], false);
                if ($k->vorlese !== $vorher) {
                    // Aufnahme passt nicht mehr zum Text
                    Erklaert::audioLoeschen($s->id);
                    $k->audio_text = $k->audio_pruefung = null;
                }
                $k->save(false);
            }
            if ($tun === 'entwurf' && is_file(Erklaert::pdf($s->id)) && !$k->istFreigegeben()) {
                $k->setAttributes(['arbeit' => 'entwurf', 'fehler' => null], false);
                $k->save(false);
                Yii::$app->queue->push(new EntwurfJob(['sitzungId' => $s->id]));
            } elseif ($tun === 'audio' && $k->vorlese) {
                $k->setAttributes(['arbeit' => 'audio', 'fehler' => null], false);
                $k->save(false);
                Yii::$app->queue->push(new AudioJob(['sitzungId' => $s->id]));
            } elseif ($tun === 'freigeben' && $k->kurz && $k->lang) {
                $n = Erklaert::freigeben($k, $user);
                $this->view->success(Yii::t('StadtratModule.base', 'Veröffentlicht. Push an {n} Konten.', ['n' => $n]));
            } elseif ($tun === 'zurueckziehen' && $k->istFreigegeben()) {
                $k->status = 'entwurf';
                $k->save(false);
                Yii::$app->cache->delete('ki-assistent-context-aktuell');
            } elseif ($tun === 'speichern') {
                $this->view->saved();
            }
            return $this->redirect(['bearbeiten', 'id' => $s->id]);
        }
        $this->view->setPageTitle(Yii::t('StadtratModule.base', 'Stadtrat kurz erklärt'));
        return $this->render('bearbeiten', [
            's' => $s,
            'k' => $k,
            'laeuft' => $laeuft,
            'hatPdf' => is_file(Erklaert::pdf($s->id)),
            'hatAudio' => Erklaert::audioDatei($s->id) !== null,
        ]);
    }

    /** Aufnahme zum Anhören vor der Freigabe */
    public function actionHoeren($id)
    {
        $datei = Erklaert::audioDatei((int)$id);
        if ($datei === null) {
            throw new NotFoundHttpException();
        }
        return Yii::$app->response->sendFile($datei, "stadtrat-$id." . pathinfo($datei, PATHINFO_EXTENSION), ['mimeType' => Erklaert::mimeType($datei), 'inline' => true]);
    }
}
