<?php

namespace humhub\modules\mitteilungsblatt;

use Yii;
use yii\helpers\Url;

class Events
{
    /** Im Space „Rathaus Uffenheim“: Menüpunkt zum Mitteilungsblatt */
    public static function onSpaceMenuInit($event)
    {
        try {
            /** @var Module $module */
            $module = Yii::$app->getModule('mitteilungsblatt');
            if ($event->sender->space->url !== $module->space || !$module->darfLesen()) {
                return;
            }
            $event->sender->addItem([
                'label' => 'Mitteilungsblatt',
                'group' => 'modules',
                'url' => Url::to(['/mitteilungsblatt/index']),
                'icon' => '<i class="fa fa-newspaper-o"></i>',
                'sortOrder' => 55,
                'isActive' => false,
            ]);
        } catch (\Throwable $e) {
            Yii::error($e);
        }
    }
}
