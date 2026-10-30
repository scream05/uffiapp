<?php

namespace humhub\modules\designwahl\controllers;

use humhub\components\Controller;
use humhub\modules\calendar\models\CalendarEntry;
use humhub\modules\file\converter\PreviewImage;
use humhub\modules\file\models\File;
use Yii;

/**
 * Angaben für die Terminkarten der Kalenderliste (resources/kalender/kalender-liste.js), die FullCalendar nicht mitliefert:
 * /designwahl/kalender/details?ids[]=98&ids[]=99 → {98: {ort, gruppe, farbe, bild}}
 * Nur Termine, die der Aufrufer sehen darf (auch Gäste); Bild = Vorschau des ersten angehängten Bildes (meist das Plakat).
 */
class KalenderController extends Controller
{
    protected function getAccessRules()
    {
        return []; // auch für Gäste, Sichtbarkeit prüft canView je Termin
    }

    public function actionDetails()
    {
        $ids = array_slice(array_unique(array_map('intval', (array)Yii::$app->request->get('ids', []))), 0, 100);
        $result = [];
        if ($ids === []) {
            return $this->asJson($result);
        }
        $bilder = [];
        foreach (File::find()->where(['object_model' => CalendarEntry::class, 'object_id' => $ids])
                     ->andWhere(['like', 'mime_type', 'image/%', false])->orderBy(['id' => SORT_ASC])->all() as $file) {
            $bilder[$file->object_id] ??= $file;
        }
        foreach (CalendarEntry::find()->where(['calendar_entry.id' => $ids])->all() as $entry) {
            if (!$entry->content->canView()) {
                continue;
            }
            $typ = $entry->getEventType();
            $bild = null;
            if (isset($bilder[$entry->id])) {
                $vorschau = new PreviewImage();
                if ($vorschau->applyFile($bilder[$entry->id])) {
                    $bild = $vorschau->getUrl();
                }
            }
            $result[$entry->id] = [
                'ort' => (string)$entry->location,
                'gruppe' => $typ->isNewRecord ? '' : Yii::t('DesignwahlModule.base', $typ->name),
                'farbe' => $typ->isNewRecord ? '' : (string)$typ->color,
                'bild' => $bild,
            ];
        }
        return $this->asJson($result);
    }

    /**
     * „In meinen Kalender“ (Detailansicht): /designwahl/kalender/ics?id=98 – ein Termin als iCal, auch für Gäste.
     * Anders als calendar/export/event mit „inline“ statt „attachment“: Die Web-App am iPhone (Startbildschirm) kann
     * keine Downloads, ein inline ausgelieferter text/calendar im neuen Fenster zeigt dagegen „Zum Kalender hinzufügen“.
     */
    public function actionIcs($id)
    {
        $entry = CalendarEntry::findOne(['id' => (int)$id]);
        if ($entry === null || !$entry->content->canView()) {
            throw new \yii\web\NotFoundHttpException();
        }
        $ics = $entry->generateIcs(true);
        if (empty($ics)) {
            throw new \yii\web\NotFoundHttpException();
        }
        $name = trim(preg_replace('/[^\p{L}\p{N}]+/u', '-', mb_strtolower($entry->title)), '-') ?: 'termin';
        return Yii::$app->response->sendContentAsFile(AboController::ohnePersonen($ics), mb_substr($name, 0, 60) . '.ics', [
            'mimeType' => 'text/calendar',
            'inline' => true,
        ]);
    }
}
