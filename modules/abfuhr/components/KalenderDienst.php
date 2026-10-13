<?php

namespace humhub\modules\abfuhr\components;

use DateTime;
use humhub\modules\calendar\interfaces\CalendarService;
use humhub\modules\calendar\interfaces\event\legacy\CalendarEventIFWrapper;
use humhub\modules\content\components\ContentActiveRecord;
use humhub\modules\content\components\ContentContainerActiveRecord;
use humhub\modules\external_calendar\models\ExternalCalendar;
use humhub\modules\space\models\Space;
use Yii;

/**
 * Kalenderdienst des Kalendermoduls, erweitert um: Abfuhrtermine (alle Kalender im Abfall-Space)
 * erscheinen nur im Kalender dieses Space – nicht auf der Übersicht („Nächste Termine“) und nicht im globalen Kalender.
 * Eingebunden per DI-Container (Events::onBeforeRequest), das Kalendermodul selbst bleibt unverändert.
 */
class KalenderDienst extends CalendarService
{
    /** @var array{0:int,1:string[]}|null contentcontainer_id des Space und Typ-Schlüssel seiner externen Kalender */
    private $abfall;

    public function getCalendarItems(?DateTime $start = null, ?DateTime $end = null, $filters = [], ?ContentContainerActiveRecord $contentContainer = null, $limit = null, $expand = true, $types = [])
    {
        if ($contentContainer !== null || !(Yii::$app instanceof \yii\web\Application) || ($abfall = $this->abfall()) === null) {
            return parent::getCalendarItems($start, $end, $filters, $contentContainer, $limit, $expand, $types);
        }
        // Ohne Begrenzung holen, erst nach dem Filtern kürzen (sonst fehlen auf der Übersicht Termine)
        $items = array_values(array_filter(
            parent::getCalendarItems($start, $end, $filters, null, null, $expand, $types),
            fn($item) => !$this->istAbfall($item, $abfall),
        ));
        return ($limit !== null && count($items) > $limit) ? array_slice($items, 0, $limit) : $items;
    }

    private function istAbfall($item, array $abfall): bool
    {
        [$containerId, $typen] = $abfall;
        if ($item instanceof CalendarEventIFWrapper) {
            $typ = $item->getEventType();
            return $typ !== null && in_array($typ->getKey(), $typen, true);
        }
        if ($item instanceof ContentActiveRecord) {
            return (int) $item->content->contentcontainer_id === $containerId;
        }
        return false;
    }

    private function abfall(): ?array
    {
        if ($this->abfall === null) {
            $space = Space::findOne(['url' => Yii::$app->getModule('abfuhr')->spaceUrl]);
            if ($space === null) {
                return null;
            }
            $typen = [];
            if (class_exists(ExternalCalendar::class)) {
                foreach (ExternalCalendar::find()->contentContainer($space)->all() as $kalender) {
                    $typen[] = $kalender->getItemTypeKey();
                }
            }
            $this->abfall = [(int) $space->contentcontainer_id, $typen];
        }
        return $this->abfall;
    }
}
