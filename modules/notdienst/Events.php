<?php

namespace humhub\modules\notdienst;

use humhub\modules\notdienst\widgets\NotdienstSidebar;
use humhub\modules\ui\menu\MenuLink;
use Yii;

class Events
{
    /** Startseite: Kasten mit Notrufnummern und heutiger Notdienstapotheke */
    public static function onDashboardSidebarInit($event)
    {
        try {
            $event->sender->addWidget(NotdienstSidebar::class, [], ['sortOrder' => 50]);
        } catch (\Throwable $e) {
            Yii::error($e);
        }
    }

    /** Im Space „Rathaus Uffenheim“: Menüpunkt zur Notdienst-Seite */
    public static function onSpaceMenuInit($event)
    {
        try {
            if ($event->sender->space->url !== Yii::$app->getModule('notdienst')->spaceUrl) {
                return;
            }
            $event->sender->addEntry(new MenuLink([
                'label' => Yii::t('NotdienstModule.base', 'Notdienste'),
                'url' => $event->sender->space->createUrl('/notdienst/index'),
                'icon' => 'medkit',
                'sortOrder' => 60,
                'isActive' => Yii::$app->controller?->module?->id === 'notdienst',
            ]));
        } catch (\Throwable $e) {
            Yii::error($e);
        }
    }
}
