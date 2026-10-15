<?php

namespace humhub\modules\gewerbe;

use humhub\modules\gewerbe\models\BetriebVerwalter;
use Yii;

class Events
{
    public static function onUserLoeschen($event)
    {
        try {
            $user = $event->user ?? $event->sender;
            BetriebVerwalter::deleteAll(['user_id' => $user->id]);
        } catch (\Throwable $e) {
            Yii::error($e);
        }
    }

    /** Betriebe in der Suche der Kopfleiste (auch für Gäste; das Verzeichnis ist öffentlich) */
    public static function onMetaSearchInit($event)
    {
        if (Yii::$app->getModule('gewerbe')->getDirectorySpace() !== null) {
            $event->sender->addProvider(\humhub\modules\gewerbe\search\BetriebSearchProvider::class);
        }
    }

    /** Im Gewerbe-Space gibt es keine Stream-Beiträge: Menüpunkt „Stream“ ausblenden */
    public static function onSpaceMenuRun($event)
    {
        try {
            $menu = $event->sender;
            if (!$menu->space->moduleManager->isEnabled('gewerbe')) {
                return;
            }
            $stream = $menu->space->createUrl('/space/space/home');
            foreach ($menu->getEntries() as $entry) {
                if ($entry instanceof \humhub\modules\ui\menu\MenuLink && $entry->getUrl() === $stream) {
                    $menu->removeEntry($entry);
                }
            }
        } catch (\Throwable $e) {
            Yii::error($e);
        }
    }

    public static function onSpaceMenuInit($event)
    {
        try {
            $space = $event->sender->space;
            if (!$space->moduleManager->isEnabled('gewerbe')) {
                return;
            }
            $active = Yii::$app->controller->module && Yii::$app->controller->module->id === 'gewerbe';
            $event->sender->addItem([
                'label' => 'Gewerbeverzeichnis',
                'group' => 'modules',
                'url' => $space->createUrl('/gewerbe/betrieb/index'),
                'icon' => '<i class="fa fa-shopping-bag"></i>',
                'isActive' => $active && Yii::$app->controller->action->id !== 'anfragen',
                'sortOrder' => 90,
            ]);
            if (Module::canManageAll()) {
                $open = BetriebVerwalter::find()->where(['status' => BetriebVerwalter::STATUS_ANGEFRAGT])->count();
                $event->sender->addItem([
                    'label' => 'Gewerbe-Anfragen' . ($open ? " ($open)" : ''),
                    'group' => 'modules',
                    'url' => $space->createUrl('/gewerbe/betrieb/anfragen'),
                    'icon' => '<i class="fa fa-inbox"></i>',
                    'isActive' => $active && Yii::$app->controller->action->id === 'anfragen',
                    'sortOrder' => 91,
                ]);
            }
        } catch (\Throwable $e) {
            Yii::error($e);
        }
    }
}
