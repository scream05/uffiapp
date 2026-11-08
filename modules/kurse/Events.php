<?php

namespace humhub\modules\kurse;

use humhub\modules\ui\menu\MenuLink;
use Yii;

class Events
{
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
