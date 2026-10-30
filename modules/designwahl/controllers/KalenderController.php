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
}
