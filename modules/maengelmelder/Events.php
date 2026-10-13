<?php

namespace humhub\modules\maengelmelder;

use humhub\modules\maengelmelder\widgets\StatusAddon;
use humhub\modules\post\models\Post;
use humhub\modules\space\models\Space;
use Yii;

class Events
{
    /** Status-Etikett unter jedem Beitrag in Spaces mit aktivem Mängelmelder */
    public static function onWallEntryAddonsInit($event)
    {
        try {
            $object = $event->sender->object;
            if (!$object instanceof Post) {
                return;
            }
            $container = $object->content->container;
            if (!$container instanceof Space || !$container->moduleManager->isEnabled('maengelmelder')) {
                return;
            }
            $event->sender->addWidget(StatusAddon::class, ['post' => $object], ['sortOrder' => 5]);
        } catch (\Throwable $e) {
            Yii::error($e);
        }
    }

    public static function onSpaceMenuInit($event)
    {
        try {
            $space = $event->sender->space;
            if (!$space->moduleManager->isEnabled('maengelmelder')) {
                return;
            }
            $active = Yii::$app->controller->module && Yii::$app->controller->module->id === 'maengelmelder';
            $event->sender->addItem([
                'label' => 'Mangel melden',
                'group' => 'modules',
                'url' => $space->createUrl('/maengelmelder/meldung/create'),
                'icon' => '<i class="fa fa-map-marker"></i>',
                'isActive' => $active && Yii::$app->controller->action->id === 'create',
                'sortOrder' => 150,
            ]);
            $event->sender->addItem([
                'label' => 'Karte',
                'group' => 'modules',
                'url' => $space->createUrl('/maengelmelder/meldung/karte'),
                'icon' => '<i class="fa fa-map"></i>',
                'isActive' => $active && Yii::$app->controller->action->id === 'karte',
                'sortOrder' => 151,
            ]);
            if (models\MaengelStatus::canManage() && $space->url === Yii::$app->getModule('maengelmelder')->bauhofSpace) {
                $neu = models\BauhofNachricht::ungelesen();
                $link = \humhub\modules\ui\menu\MenuLink::createByArray([
                    'label' => 'Bauhof' . ($neu ? ' <span class="badge bg-danger">' . $neu . '</span>' : ''),
                    'url' => $space->createUrl('/maengelmelder/bauhof/index'),
                    'icon' => '<i class="fa fa-truck"></i>',
                    'isActive' => $active && Yii::$app->controller->id === 'bauhof',
                    'sortOrder' => 152,
                ]);
                $link->setEncodeLabel(false);
                $event->sender->addEntry($link);
            }
        } catch (\Throwable $e) {
            Yii::error($e);
        }
    }
}
