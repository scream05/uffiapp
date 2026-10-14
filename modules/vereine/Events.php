<?php

namespace humhub\modules\vereine;

use humhub\modules\calendar\models\CalendarEntry;
use humhub\modules\post\models\Post;
use humhub\modules\vereine\models\VereinBeitrag;
use humhub\modules\vereine\models\VereinVerwalter;
use humhub\modules\vereine\widgets\VereinAddon;
use Yii;

class Events
{
    public static function onMetaSearchInit($event)
    {
        if (!Yii::$app->user->isGuest && Yii::$app->getModule('vereine')->getDirectorySpace() !== null) {
            $event->sender->addProvider(\humhub\modules\vereine\search\VereinSearchProvider::class);
        }
    }

    public static function onSpaceMenuInit($event)
    {
        try {
            $space = $event->sender->space;
            if (!$space->moduleManager->isEnabled('vereine')) {
                return;
            }
            $active = Yii::$app->controller->module && Yii::$app->controller->module->id === 'vereine';
            $event->sender->addItem([
                'label' => 'Vereinsverzeichnis',
                'group' => 'modules',
                'url' => $space->createUrl('/vereine/verein/index'),
                'icon' => '<i class="fa fa-users"></i>',
                'isActive' => $active && !in_array(Yii::$app->controller->action->id, ['anfragen', 'pruefen'], true),
                'sortOrder' => 90,
            ]);
            if (Module::canManageAll()) {
                $open = VereinVerwalter::find()->where(['status' => VereinVerwalter::STATUS_ANGEFRAGT])->count();
                $event->sender->addItem([
                    'label' => 'Vereins-Anfragen' . ($open ? " ($open)" : ''),
                    'group' => 'modules',
                    'url' => $space->createUrl('/vereine/verein/anfragen'),
                    'icon' => '<i class="fa fa-inbox"></i>',
                    'isActive' => $active && Yii::$app->controller->action->id === 'anfragen',
                    'sortOrder' => 91,
                ]);
                $offen = VereinBeitrag::find()->where(['status' => VereinBeitrag::STATUS_EINGEREICHT])->count();
                $event->sender->addItem([
                    'label' => 'Vereins-Beiträge' . ($offen ? " ($offen)" : ''),
                    'group' => 'modules',
                    'url' => $space->createUrl('/vereine/beitrag/pruefen'),
                    'icon' => '<i class="fa fa-check-square-o"></i>',
                    'isActive' => $active && Yii::$app->controller->action->id === 'pruefen',
                    'sortOrder' => 92,
                ]);
            }
        } catch (\Throwable $e) {
            Yii::error($e);
        }
    }

    /** „Veranstalter: Verein“ unter Vereinsterminen im gemeinsamen Kalender */
    public static function onWallEntryAddonsInit($event)
    {
        try {
            $object = $event->sender->object;
            if ($object instanceof CalendarEntry) {
                $event->sender->addWidget(VereinAddon::class, ['entry' => $object], ['sortOrder' => 5]);
            } elseif ($object instanceof Post && ($b = VereinBeitrag::forContent($object->content->id)) !== null) {
                $event->sender->addWidget(VereinAddon::class, ['beitrag' => $b], ['sortOrder' => 5]);
            }
        } catch (\Throwable $e) {
            Yii::error($e);
        }
    }
}
