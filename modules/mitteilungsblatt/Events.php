<?php

namespace humhub\modules\mitteilungsblatt;

use Yii;

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
                'label' => Yii::t('MitteilungsblattModule.base', 'Mitteilungsblatt'),
                'group' => 'modules',
                'url' => $event->sender->space->createUrl('/mitteilungsblatt/index'),
                'icon' => '<i class="fa fa-newspaper-o"></i>',
                'sortOrder' => 55,
                'isActive' => Yii::$app->controller?->module?->id === 'mitteilungsblatt',
            ]);
        } catch (\Throwable $e) {
            Yii::error($e);
        }
    }
}
