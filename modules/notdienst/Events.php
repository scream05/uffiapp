<?php

namespace humhub\modules\notdienst;

use humhub\modules\notdienst\widgets\NotdienstSidebar;
use Yii;
use yii\helpers\Url;

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
            $event->sender->addItem([
                'label' => 'Notdienste',
                'group' => 'modules',
                'url' => Url::to(['/notdienst/index']),
                'icon' => '<i class="fa fa-medkit"></i>',
                'sortOrder' => 60,
                'isActive' => false,
            ]);
        } catch (\Throwable $e) {
            Yii::error($e);
        }
    }
}
