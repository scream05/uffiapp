<?php

namespace humhub\modules\oeffnungszeiten;

use humhub\modules\oeffnungszeiten\widgets\Kasten;
use humhub\modules\ui\menu\MenuLink;
use Yii;

class Events
{
    /** Startseite: Kasten „Öffnungszeiten“ mit Anzeige „jetzt geöffnet“ (am Handy im Fach rechts) */
    public static function onDashboardSidebarInit($event)
    {
        try {
            $event->sender->addWidget(Kasten::class, [], ['sortOrder' => 55]);
        } catch (\Throwable $e) {
            Yii::error($e);
        }
    }

    /** Im Space „Rathaus Uffenheim“: Menüpunkt zur Seite */
    public static function onSpaceMenuInit($event)
    {
        try {
            if ($event->sender->space->url !== Yii::$app->getModule('oeffnungszeiten')->spaceUrl) {
                return;
            }
            $event->sender->addEntry(new MenuLink([
                'label' => Yii::t('OeffnungszeitenModule.base', 'Öffnungszeiten'),
                'url' => $event->sender->space->createUrl('/oeffnungszeiten/index'),
                'icon' => 'clock-o',
                'sortOrder' => 55,
                'isActive' => Yii::$app->controller?->module?->id === 'oeffnungszeiten',
            ]));
        } catch (\Throwable $e) {
            Yii::error($e);
        }
    }
}
