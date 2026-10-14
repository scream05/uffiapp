<?php

namespace humhub\modules\whatsapp\controllers;

use humhub\modules\content\components\ContentContainerController;
use humhub\modules\whatsapp\components\Verarbeitung;
use humhub\modules\whatsapp\models\Entwurf;
use humhub\modules\whatsapp\models\Nachricht;
use humhub\modules\whatsapp\Module;
use Yii;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;

/**
 * Eingang im Space „Veranstaltungen“: erkannte Termine prüfen, korrigieren, übernehmen oder ablehnen (nur Admins).
 *
 * @property Module $module
 */
class EingangController extends ContentContainerController
{
    protected function getAccessRules()
    {
        return [['login']];
    }

    public function beforeAction($action)
    {
        if (!parent::beforeAction($action)) {
            return false;
        }
        if (!Module::canReview()) {
            throw new ForbiddenHttpException('Nur für Admins.');
        }
        return true;
    }

    public function actionIndex()
    {
        $mitEntwurf = Entwurf::find()->select('gruppe')->distinct()->column();
        // Einreichungen ohne erkannten Termin (oder mit Fehler) der letzten 14 Tage, die noch niemand angesehen hat
        $ohne = Nachricht::find()->where(['status' => [Nachricht::STATUS_VERARBEITET, Nachricht::STATUS_FEHLER]])
            ->andWhere(['not', ['gruppe' => null]])->andWhere(['not in', 'gruppe', $mitEntwurf ?: ['']])
            ->andWhere(['>=', 'empfangen_at', date('Y-m-d', strtotime('-14 days'))])
            ->orderBy(['empfangen_at' => SORT_DESC])->all();
        $gruppen = [];
        foreach ($ohne as $n) {
            $gruppen[$n->gruppe][] = $n;
        }
        return $this->render('index', [
            'offen' => Entwurf::find()->where(['status' => Entwurf::STATUS_OFFEN])->orderBy(['start_datum' => SORT_ASC])->all(),
            'ohne' => $gruppen,
            'erledigt' => Entwurf::find()->where(['not', ['status' => Entwurf::STATUS_OFFEN]])
                ->orderBy(['bearbeitet_at' => SORT_DESC])->limit(15)->all(),
            'wartend' => Nachricht::find()->where(['status' => Nachricht::STATUS_NEU])->count(),
            'usage' => $this->module->getUsage(),
        ]);
    }

    public function actionBearbeiten($id)
    {
        $entwurf = $this->findOffen($id);
        $space = $this->contentContainer;
        $aehnliche = [];
        if ($entwurf->load(Yii::$app->request->post())) {
            $aehnliche = $entwurf->trotzdem ? [] : $entwurf->aehnliche($space);
            if ($aehnliche === [] && ($entry = $entwurf->uebernehmen($space))) {
                $gesendet = Verarbeitung::antworten($entwurf->wa_id, "✅ Ist jetzt im Kalender: {$entwurf->titel} – "
                    . Verarbeitung::wann($entwurf) . "\n" . $entry->content->getUrl(true) . "\n\nDanke fürs Teilen!");
                $this->view->success('Termin übernommen.' . ($gesendet ? ' Absender wurde per WhatsApp informiert.' : ''));
                return $this->redirect($space->createUrl('/whatsapp/eingang/index'));
            }
        } elseif ($entwurf->titel !== '' && $entwurf->validate()) {
            // Fehler in den erkannten Angaben gleich zeigen (bei „Termin anlegen“ ist alles leer)
            $aehnliche = $entwurf->aehnliche($space);
        }
        return $this->render('bearbeiten', [
            'entwurf' => $entwurf,
            'aehnliche' => $aehnliche,
            'typen' => Entwurf::typen($space),
            'nachrichten' => Nachricht::find()->where(['gruppe' => $entwurf->gruppe])->orderBy(['empfangen_at' => SORT_ASC, 'id' => SORT_ASC])->all(),
        ]);
    }

    public function actionAblehnen($id)
    {
        $this->forcePostRequest();
        $this->findOffen($id)->ablehnen();
        $this->view->success('Entwurf abgelehnt.');
        return $this->redirect($this->contentContainer->createUrl('/whatsapp/eingang/index'));
    }

    /** Einreichung ohne erkannten Termin: Termin von Hand anlegen */
    public function actionAnlegen($gruppe)
    {
        $this->forcePostRequest();
        $n = Nachricht::findOne(['gruppe' => (string)$gruppe]) ?? throw new NotFoundHttpException();
        $entwurf = new Entwurf(['gruppe' => $n->gruppe, 'wa_id' => $n->wa_id, 'absender_name' => $n->absender_name,
            'titel' => '', 'status' => Entwurf::STATUS_OFFEN, 'created_at' => date('Y-m-d H:i:s')]);
        $entwurf->save(false);
        return $this->redirect($this->contentContainer->createUrl('/whatsapp/eingang/bearbeiten', ['id' => $entwurf->id]));
    }

    /** Einreichung ohne Termin ausblenden */
    public function actionVerwerfen($gruppe)
    {
        $this->forcePostRequest();
        Nachricht::updateAll(['status' => Nachricht::STATUS_IGNORIERT], ['gruppe' => (string)$gruppe]);
        return $this->redirect($this->contentContainer->createUrl('/whatsapp/eingang/index'));
    }

    /** Bild/PDF einer Nachricht (liegt außerhalb des Webroots) */
    public function actionDatei($id)
    {
        $n = Nachricht::findOne((int)$id);
        if ($n === null || !$n->getPfad() || !is_file($n->getPfad())) {
            throw new NotFoundHttpException();
        }
        // Typ nach Inhalt; angezeigt werden nur Rasterbilder und PDF, alles andere als Download (keine SVG mit Skripten)
        $pfad = $n->getPfad();
        $typ = (string)((new \finfo(FILEINFO_MIME_TYPE))->file($pfad) ?: 'application/octet-stream');
        $response = Yii::$app->response;
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        if (in_array($typ, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true)) {
            $response->headers->set('Content-Security-Policy', "default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; sandbox");
        }
        $inline = in_array($typ, ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf'], true);
        return $response->sendFile($pfad, $n->datei, ['mimeType' => $inline ? $typ : 'application/octet-stream', 'inline' => $inline]);
    }

    private function findOffen($id): Entwurf
    {
        $entwurf = Entwurf::findOne((int)$id);
        if ($entwurf === null || $entwurf->status !== Entwurf::STATUS_OFFEN) {
            throw new NotFoundHttpException('Entwurf nicht gefunden oder schon bearbeitet.');
        }
        return $entwurf;
    }
}
