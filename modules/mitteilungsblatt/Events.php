<?php

namespace humhub\modules\mitteilungsblatt;

use Yii;
use humhub\modules\ui\menu\MenuLink;

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
            $event->sender->addEntry(new MenuLink([
                'label' => Yii::t('MitteilungsblattModule.base', 'Mitteilungsblatt'),
                'url' => $event->sender->space->createUrl('/mitteilungsblatt/index'),
                'icon' => 'newspaper-o',
                'sortOrder' => 55,
                'isActive' => Yii::$app->controller?->module?->id === 'mitteilungsblatt' && Yii::$app->controller->action?->id !== 'bekanntmachungen',
            ]));
            if ($module->darfBekanntmachungenSehen()) {
                $event->sender->addEntry(new MenuLink([
                    'label' => Yii::t('MitteilungsblattModule.base', 'Bekanntmachungen'),
                    'url' => $event->sender->space->createUrl('/mitteilungsblatt/index/bekanntmachungen'),
                    'icon' => 'gavel',
                    'sortOrder' => 56,
                    'isActive' => Yii::$app->controller?->module?->id === 'mitteilungsblatt' && Yii::$app->controller->action?->id === 'bekanntmachungen',
                ]));
            }
        } catch (\Throwable $e) {
            Yii::error($e);
        }
    }
}
