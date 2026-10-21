<?php

namespace humhub\modules\vorschlaege;

use humhub\modules\post\models\Post;
use humhub\modules\space\models\Space;
use humhub\modules\vorschlaege\models\Vorschlag;
use humhub\modules\vorschlaege\widgets\VorschlagAddon;
use Yii;

class Events
{
    public static function onUserLoeschen($event)
    {
        try {
            $user = $event->user ?? $event->sender;
            models\Stimme::deleteAll(['user_id' => $user->id]);
        } catch (\Throwable $e) {
            Yii::error($e, 'vorschlaege');
        }
    }

    /** Stand und Abstimmung unter jedem Vorschlag */
    public static function onWallEntryAddonsInit($event)
    {
        try {
            $object = $event->sender->object;
            if (!$object instanceof Post || !$object->content->container instanceof Space
                || !$object->content->container->moduleManager->isEnabled('vorschlaege')) {
                return;
            }
            $vorschlag = Vorschlag::findOne(['content_id' => $object->content->id]);
            if ($vorschlag !== null) {
                $event->sender->addWidget(VorschlagAddon::class, ['vorschlag' => $vorschlag], ['sortOrder' => 5]);
            }
        } catch (\Throwable $e) {
            Yii::error($e, 'vorschlaege');
        }
    }

    public static function onSpaceMenuInit($event)
    {
        try {
            $space = $event->sender->space;
            if (!$space->moduleManager->isEnabled('vorschlaege')) {
                return;
            }
            $c = Yii::$app->controller;
            $aktiv = ($c->module->id ?? null) === 'vorschlaege';
            $event->sender->addItem([
                'label' => Yii::t('VorschlaegeModule.base', 'Alle Vorschläge'),
                'group' => 'modules',
                'url' => $space->createUrl('/vorschlaege/vorschlag/index'),
                'icon' => '<i class="fa fa-lightbulb-o"></i>',
                'isActive' => $aktiv && $c->action->id === 'index',
                'sortOrder' => 150,
            ]);
            if (!Yii::$app->user->isGuest) {
                $event->sender->addItem([
                    'label' => Yii::t('VorschlaegeModule.base', 'Vorschlag einreichen'),
                    'group' => 'modules',
                    'url' => $space->createUrl('/vorschlaege/vorschlag/create'),
                    'icon' => '<i class="fa fa-plus"></i>',
                    'isActive' => $aktiv && $c->action->id === 'create',
                    'sortOrder' => 151,
                ]);
            }
        } catch (\Throwable $e) {
            Yii::error($e, 'vorschlaege');
        }
    }
}
