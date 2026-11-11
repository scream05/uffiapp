<?php

namespace humhub\modules\kurse;

use humhub\modules\ui\menu\MenuLink;
use Yii;

class Events
{
    /**
     * Eigene Kursseite eines Vereins: /kurse/<slug>, Kurs /kurse/<slug>/kurs/<id>, Anmeldung …/anmelden,
     * Datenschutz …/datenschutz, Mail-Links /kurse/<slug>/bestaetigen?t= und /kurse/<slug>/anmeldung?t=
     */
    public static function onBeforeRequest($event)
    {
        try {
            $s = '<slug:[a-z0-9][a-z0-9-]{1,58}[a-z0-9]>';
            Yii::$app->urlManager->addRules([
                "kurse/$s" => 'kurse/seite/index',
                "kurse/$s/kurs/<id:\\d+>" => 'kurse/seite/kurs',
                "kurse/$s/kurs/<id:\\d+>/anmelden" => 'kurse/seite/anmelden',
                "kurse/$s/kurs/<id:\\d+>/datenschutz" => 'kurse/seite/datenschutz',
                "kurse/$s/datenschutz" => 'kurse/seite/datenschutzseite',
                "kurse/$s/bestaetigen" => 'kurse/seite/bestaetigen',
                "kurse/$s/anmeldung" => 'kurse/seite/status',
            ], false);
        } catch (\Throwable $e) {
            Yii::error($e, 'kurse');
        }
    }

    /** Menüpunkt „Kurse“ im Space des Vereinsverzeichnisses */
    public static function onSpaceMenuInit($event)
    {
        try {
            $space = $event->sender->space;
            if (!$space->moduleManager->isEnabled('kurse')) {
                return;
            }
            /** @var Module $module */
            $module = Yii::$app->getModule('kurse');
            if (!$module->kannSehen()) {
                return;
            }
            $event->sender->addEntry(new MenuLink([
                'label' => 'Kurse',
                'url' => $space->createUrl('/kurse/kurs/index'),
                'icon' => 'graduation-cap',
                'isActive' => Yii::$app->controller->module && Yii::$app->controller->module->id === 'kurse',
                'sortOrder' => 88,
            ]));
        } catch (\Throwable $e) {
            Yii::error($e);
        }
    }
}
