<?php

namespace humhub\modules\stadtrat;

use humhub\modules\stadtrat\widgets\NaechsteSitzung;
use humhub\modules\ui\menu\MenuLink;
use Yii;

class Events
{
    /** Startseite: Kasten „Nächste Sitzung“ (nur freigegebene Konten, siehe Module::$freigabe) */
    public static function onDashboardSidebarInit($event)
    {
        try {
            if (Yii::$app->getModule('stadtrat')->istFreigegeben(Yii::$app->user->getIdentity())) {
                $event->sender->addWidget(NaechsteSitzung::class, [], ['sortOrder' => 70]);
            }
        } catch (\Throwable $e) {
            Yii::error($e, 'stadtrat');
        }
    }

    /** Im Space „Rathaus Uffenheim“: Menüpunkt „Stadtrat“ */
    public static function onSpaceMenuInit($event)
    {
        try {
            $modul = Yii::$app->getModule('stadtrat');
            if ($event->sender->space->url !== $modul->spaceUrl || !$modul->istFreigegeben(Yii::$app->user->getIdentity())) {
                return;
            }
            $event->sender->addEntry(new MenuLink([
                'label' => Yii::t('StadtratModule.base', 'Stadtrat'),
                'url' => $event->sender->space->createUrl('/stadtrat/index'),
                'icon' => 'university',
                'sortOrder' => 60,
                'isActive' => Yii::$app->controller?->module?->id === 'stadtrat',
            ]));
        } catch (\Throwable $e) {
            Yii::error($e, 'stadtrat');
        }
    }
}
