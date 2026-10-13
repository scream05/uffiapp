<?php

namespace humhub\modules\abfuhr;

use Yii;
use yii\helpers\Url;

class Events
{
    public static function onAccountMenuInit($event)
    {
        try {
            $event->sender->addItem([
                'label' => 'Abfuhr-Erinnerung',
                'url' => Url::to(['/abfuhr/einstellungen']),
                'icon' => 'trash',
                'sortOrder' => 450,
                'isActive' => Yii::$app->controller->module && Yii::$app->controller->module->id === 'abfuhr',
            ]);
        } catch (\Throwable $e) {
            Yii::error($e);
        }
    }

    /** Im Space „Abfall & Termine“: Menüpunkt zur Erinnerung */
    public static function onSpaceMenuInit($event)
    {
        try {
            $space = $event->sender->space;
            if ($space->name !== Yii::$app->getModule('abfuhr')->spaceName) {
                return;
            }
            $event->sender->addItem([
                'label' => 'Erinnerung einrichten',
                'group' => 'modules',
                'url' => Url::to(['/abfuhr/einstellungen']),
                'icon' => '<i class="fa fa-bell"></i>',
                'sortOrder' => 50,
                'isActive' => false,
            ]);
        } catch (\Throwable $e) {
            Yii::error($e);
        }
    }
}
