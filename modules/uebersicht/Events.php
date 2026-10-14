<?php

namespace humhub\modules\uebersicht;

use humhub\modules\uebersicht\assets\UebersichtAsset;
use humhub\modules\uebersicht\filters\AngepinntFilter;
use humhub\modules\uebersicht\widgets\PinLink;
use Yii;

class Events
{
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
            $view->registerJsConfig('uebersicht', ['ids' => $module->getIds()]);
        } catch (\Throwable $e) {
            Yii::error($e);
        }
    }
}
