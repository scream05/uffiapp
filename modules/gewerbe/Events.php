<?php

namespace humhub\modules\gewerbe;

use humhub\modules\gewerbe\models\BetriebBeitrag;
use humhub\modules\gewerbe\models\BetriebVerwalter;
use humhub\modules\gewerbe\models\Einloesung;
use humhub\modules\gewerbe\models\Gutschein;
use humhub\modules\gewerbe\widgets\BetriebAddon;
use humhub\modules\post\models\Post;
use Yii;

class Events
{
    /** Abgelehnte Gewerbebeiträge so viele Tage nach der Prüfung löschen */
    public const ABGELEHNT_TAGE = 30;

    public static function onUserLoeschen($event)
    {
        try {
            $user = $event->user ?? $event->sender;
            BetriebVerwalter::deleteAll(['user_id' => $user->id]);
            // Einlösungen der Person (die Zähler der Gutscheine bleiben)
            Einloesung::deleteAll(['user_id' => $user->id]);
            foreach (BetriebBeitrag::find()->where(['user_id' => $user->id])
                         ->andWhere(['!=', 'status', BetriebBeitrag::STATUS_VEROEFFENTLICHT])->all() as $b) {
                $b->delete(); // samt Bildern
            }
        } catch (\Throwable $e) {
            Yii::error($e);
        }
    }

    public static function onCronDaily($event)
    {
        try {
            foreach (BetriebBeitrag::find()->where(['status' => BetriebBeitrag::STATUS_ABGELEHNT])
                         ->andWhere(['<', 'geprueft_am', date('Y-m-d H:i:s', strtotime('-' . self::ABGELEHNT_TAGE . ' days'))])->all() as $b) {
                $b->delete();
            }
            // Einlösungen braucht es nur für das Limit „pro Person“: einige Zeit nach Ende des Gutscheins löschen
            $tage = Yii::$app->getModule('gewerbe')->einloesungTage;
            $vorbei = Gutschein::find()->select('id')->where(['<', 'gueltig_bis', date('Y-m-d', strtotime("-$tage days"))])->column();
            if ($vorbei) {
                Einloesung::deleteAll(['gutschein_id' => $vorbei]);
            }
            // Zurückgenommene Einlösungen nach 30 Tagen
            Einloesung::deleteAll(['<', 'storniert_am', date('Y-m-d H:i:s', strtotime('-30 days'))]);
        } catch (\Throwable $e) {
            Yii::error($e);
        }
    }

    /** „Beitrag von <Betrieb>“ unter Gewerbebeiträgen in der Übersicht */
    public static function onWallEntryAddonsInit($event)
    {
        try {
            $object = $event->sender->object;
            if ($object instanceof Post && ($b = BetriebBeitrag::forContent($object->content->id)) !== null) {
                $event->sender->addWidget(BetriebAddon::class, ['beitrag' => $b], ['sortOrder' => 5]);
            }
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
                'label' => Yii::t('GewerbeModule.base', 'Gewerbeverzeichnis'),
                'group' => 'modules',
                'url' => $space->createUrl('/gewerbe/betrieb/index'),
                'icon' => '<i class="fa fa-shopping-bag"></i>',
                'isActive' => $active && Yii::$app->controller->id !== 'gutschein'
                    && !in_array(Yii::$app->controller->action->id, ['anfragen', 'pruefen'], true),
                'sortOrder' => 90,
            ]);
            $event->sender->addItem([
                'label' => Yii::t('GewerbeModule.base', 'Gutscheine'),
                'group' => 'modules',
                'url' => $space->createUrl('/gewerbe/gutschein/index'),
                'icon' => '<i class="fa fa-ticket"></i>',
                'isActive' => $active && Yii::$app->controller->id === 'gutschein',
                'sortOrder' => 91,
            ]);
            if (Module::canManageAll()) {
                $open = BetriebVerwalter::find()->where(['status' => BetriebVerwalter::STATUS_ANGEFRAGT])->count();
                $event->sender->addItem([
                    'label' => $open ? Yii::t('GewerbeModule.base', 'Gewerbe-Anfragen ({anzahl})', ['anzahl' => $open])
                        : Yii::t('GewerbeModule.base', 'Gewerbe-Anfragen'),
                    'group' => 'modules',
                    'url' => $space->createUrl('/gewerbe/betrieb/anfragen'),
                    'icon' => '<i class="fa fa-inbox"></i>',
                    'isActive' => $active && Yii::$app->controller->action->id === 'anfragen',
                    'sortOrder' => 92,
                ]);
                $offen = BetriebBeitrag::find()->where(['status' => BetriebBeitrag::STATUS_EINGEREICHT])->count();
                $event->sender->addItem([
                    'label' => $offen ? Yii::t('GewerbeModule.base', 'Gewerbe-Beiträge ({anzahl})', ['anzahl' => $offen])
                        : Yii::t('GewerbeModule.base', 'Gewerbe-Beiträge'),
                    'group' => 'modules',
                    'url' => $space->createUrl('/gewerbe/beitrag/pruefen'),
                    'icon' => '<i class="fa fa-check-square-o"></i>',
                    'isActive' => $active && Yii::$app->controller->action->id === 'pruefen',
                    'sortOrder' => 93,
                ]);
            }
        } catch (\Throwable $e) {
            Yii::error($e);
        }
    }
}
