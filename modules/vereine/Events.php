<?php

namespace humhub\modules\vereine;

use humhub\modules\calendar\models\CalendarEntry;
use humhub\modules\ui\menu\MenuLink;
use humhub\modules\post\models\Post;
use humhub\modules\vereine\models\Verein;
use humhub\modules\vereine\models\VereinBeitrag;
use humhub\modules\vereine\models\VereinVerwalter;
use humhub\modules\vereine\widgets\VereinAddon;
use Yii;

class Events
{
    /** Abgelehnte Vereinsbeiträge so viele Tage nach der Prüfung löschen */
    public const ABGELEHNT_TAGE = 30;

    public static function onUserLoeschen($event)
    {
        try {
            $user = $event->user ?? $event->sender;
            VereinVerwalter::deleteAll(['user_id' => $user->id]);
            foreach (VereinBeitrag::find()->where(['user_id' => $user->id])
                         ->andWhere(['!=', 'status', VereinBeitrag::STATUS_VEROEFFENTLICHT])->all() as $b) {
                $b->delete(); // samt Bildern
            }
        } catch (\Throwable $e) {
            Yii::error($e);
        }
    }

    public static function onCronDaily($event)
    {
        try {
            foreach (VereinBeitrag::find()->where(['status' => VereinBeitrag::STATUS_ABGELEHNT])
                         ->andWhere(['<', 'geprueft_am', date('Y-m-d H:i:s', strtotime('-' . self::ABGELEHNT_TAGE . ' days'))])->all() as $b) {
                $b->delete();
            }
        } catch (\Throwable $e) {
            Yii::error($e);
        }
    }

    /** Eigene Sportsymbole (assets/SportIconAsset) für Menü, Startseiten-Kasten und Verzeichnis */
    public static function onLayoutAddons($event)
    {
        try {
            if (!Yii::$app->request->isConsoleRequest) {
                \humhub\modules\vereine\assets\SportIconAsset::register(Yii::$app->view);
            }
        } catch (\Throwable $e) {
            Yii::error($e);
        }
    }

    /** Startseite: an Spieltagen Kasten mit den heutigen Spielen und dem Live-Stand (am Handy im Fach rechts) */
    public static function onDashboardSidebarInit($event)
    {
        try {
            $event->sender->addWidget(\humhub\modules\vereine\widgets\SpieltagKasten::class, [], ['sortOrder' => 52]);
        } catch (\Throwable $e) {
            Yii::error($e);
        }
    }

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
            $event->sender->addEntry(new MenuLink([
                'label' => Yii::t('VereineModule.base', 'Vereinsverzeichnis'),
                'url' => $space->createUrl('/vereine/verein/index'),
                'icon' => 'users',
                'isActive' => $active && Yii::$app->controller->id !== 'spiele' && !in_array(Yii::$app->controller->action->id, ['anfragen', 'pruefen'], true),
                'sortOrder' => 90,
            ]));
            // Ein Menüpunkt „Sport“ (Übersicht aller Vereine mit Spielplan bzw. Ergebnis-Link), darunter die Seiten je Verein
            if (Verein::find()->where(['or', ['not', ['bfv_id' => null]], ['not', ['ergebnisse_url' => null]]])->exists()) {
                $event->sender->addEntry(new MenuLink([
                    'label' => Yii::t('VereineModule.base', 'Sport'),
                    'url' => $space->createUrl('/vereine/spiele/index'),
                    'icon' => 'trophy',
                    'isActive' => $active && Yii::$app->controller->id === 'spiele',
                    'sortOrder' => 89,
                ]));
            }
            if (Module::canManageAll()) {
                $open = VereinVerwalter::find()->where(['status' => VereinVerwalter::STATUS_ANGEFRAGT])->count();
                $event->sender->addEntry(new MenuLink([
                    'label' => 'Vereins-Anfragen' . ($open ? " ($open)" : ''),
                    'url' => $space->createUrl('/vereine/verein/anfragen'),
                    'icon' => 'inbox',
                    'isActive' => $active && Yii::$app->controller->action->id === 'anfragen',
                    'sortOrder' => 91,
                ]));
                $offen = VereinBeitrag::find()->where(['status' => VereinBeitrag::STATUS_EINGEREICHT])->count();
                $event->sender->addEntry(new MenuLink([
                    'label' => 'Vereins-Beiträge' . ($offen ? " ($offen)" : ''),
                    'url' => $space->createUrl('/vereine/beitrag/pruefen'),
                    'icon' => 'check-square-o',
                    'isActive' => $active && Yii::$app->controller->action->id === 'pruefen',
                    'sortOrder' => 92,
                ]));
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
