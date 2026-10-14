<?php

namespace humhub\modules\uebersicht;

use humhub\modules\uebersicht\assets\UebersichtAsset;
use humhub\modules\uebersicht\filters\AngepinntFilter;
use humhub\modules\uebersicht\widgets\PinLink;
use humhub\modules\ui\menu\MenuLink;
use Yii;
use yii\helpers\Url;

class Events
{
    /** Übersicht angemeldeter Nutzer nach „Meine Übersicht“ (filters/MeineUebersicht) */
    public static function onBeforeRequest($event)
    {
        try {
            $dashboard = Yii::$app->getModule('dashboard');
            if ($dashboard->memberFilterClass === \humhub\modules\dashboard\stream\filters\DashboardMemberStreamFilter::class) {
                $dashboard->memberFilterClass = \humhub\modules\uebersicht\filters\MeineUebersicht::class;
            }
        } catch (\Throwable $e) {
            Yii::error($e);
        }
    }

    /** Profilmenü oben rechts: „Meine Übersicht“ */
    public static function onAccountTopMenuInit($event)
    {
        try {
            if (Yii::$app->user->isGuest) {
                return;
            }
            $event->sender->addEntry(new MenuLink([
                'label' => 'Meine Übersicht',
                'url' => Url::to(['/uebersicht/einstellungen/index']),
                'icon' => 'sliders',
                'sortOrder' => 640,
            ]));
        } catch (\Throwable $e) {
            Yii::error($e);
        }
    }

    /** Space-Kopf: HumHubs „Beiträge (nicht) in der Übersicht anzeigen“ durch Link auf „Meine Übersicht“ ersetzen */
    public static function onSpaceHeaderControlsRun($event)
    {
        try {
            $menu = $event->sender;
            $ersetzt = false;
            foreach ($menu->getEntries() as $entry) {
                if ($entry instanceof MenuLink && str_contains((string)$entry->getUrl(), 'switch-dashboard-display')) {
                    $menu->removeEntry($entry);
                    $ersetzt = true;
                }
            }
            if ($ersetzt) {
                $menu->addEntry(new MenuLink([
                    'label' => 'In meiner Übersicht anzeigen …',
                    'url' => Url::to(['/uebersicht/einstellungen/index']),
                    'icon' => 'sliders',
                    'sortOrder' => 800,
                ]));
            }
        } catch (\Throwable $e) {
            Yii::error($e);
        }
    }

    /** Übersicht: angepinnte Beiträge vorne einfügen */
    public static function onDashboardStreamBeforeFilter($event)
    {
        try {
            $event->sender->addFilterHandler(new AngepinntFilter());
        } catch (\Throwable $e) {
            Yii::error($e);
        }
    }

    /** Beitragsmenü: „In Übersicht anpinnen“ / „Aus Übersicht lösen“, nur für Admins */
    public static function onWallEntryControlsInit($event)
    {
        try {
            if (Yii::$app->user->isGuest || !Yii::$app->user->isAdmin()) {
                return;
            }
            $record = $event->sender->object;
            if ($record && $record->content && !$record->content->isNewRecord && $record->content->contentcontainer_id) {
                $event->sender->addWidget(PinLink::class, ['record' => $record], ['sortOrder' => 610]);
            }
        } catch (\Throwable $e) {
            Yii::error($e);
        }
    }

    /** Markierung „Angepinnt“ in der Übersicht (IDs für das Skript) */
    public static function onLayoutAddons($event)
    {
        try {
            if (Yii::$app->request->isConsoleRequest || !Yii::$app->controller
                || Yii::$app->controller->module->id !== 'dashboard') {
                return;
            }
            /** @var Module $module */
            $module = Yii::$app->getModule('uebersicht');
            $view = Yii::$app->view;
            UebersichtAsset::register($view);
            $view->registerJsConfig('uebersicht', ['ids' => $module->getIds(),
                'einstellungen' => Yii::$app->user->isGuest ? null : Url::to(['/uebersicht/einstellungen/index'])]);
        } catch (\Throwable $e) {
            Yii::error($e);
        }
    }
}
