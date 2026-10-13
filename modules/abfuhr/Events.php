<?php

namespace humhub\modules\abfuhr;

use humhub\modules\calendar\interfaces\CalendarService;
use humhub\modules\space\models\Space;
use humhub\modules\ui\menu\MenuLink;
use Yii;
use yii\helpers\Url;

class Events
{
    public static function onAccountMenuInit($event)
    {
        try {
            $event->sender->addItem([
                'label' => 'Abfuhr-Erinnerung',
                'url' => Url::to(['/abfuhr/einstellungen']),
                'icon' => 'trash',
                'sortOrder' => 450,
                'isActive' => Yii::$app->controller->module && Yii::$app->controller->module->id === 'abfuhr',
            ]);
        } catch (\Throwable $e) {
            Yii::error($e);
        }
    }

    /** Im Abfall-Space: Menüpunkt zur Erinnerung */
    public static function onSpaceMenuInit($event)
    {
        try {
            $space = $event->sender->space;
            if (!self::imAbfallSpace($space)) {
                return;
            }
            $event->sender->addItem([
                'label' => 'Erinnerung einrichten',
                'group' => 'modules',
                'url' => Url::to(['/abfuhr/einstellungen']),
                'icon' => '<i class="fa fa-bell"></i>',
                'sortOrder' => 50,
                'isActive' => false,
            ]);
        } catch (\Throwable $e) {
            Yii::error($e);
        }
    }

    /** Kalenderdienst mit Filter: Abfuhrtermine nur im Kalender des Space (components/KalenderDienst) */
    public static function onBeforeRequest($event)
    {
        if (class_exists(CalendarService::class)) {
            Yii::$container->set(CalendarService::class, components\KalenderDienst::class);
        }
    }

    /** Ist das der Abfall-Space („Abfalltermine“, früher „Abfall & Termine“)? */
    private static function imAbfallSpace(?Space $space): bool
    {
        return $space !== null && $space->url === Yii::$app->getModule('abfuhr')->spaceUrl;
    }

    /** Abfall-Space ist nur Kalender: kein Menüpunkt „Stream“, kein Beitragszähler im Kopf */
    public static function onSpaceMenuRun($event)
    {
        try {
            $menu = $event->sender;
            if (!self::imAbfallSpace($menu->space)) {
                return;
            }
            $stream = $menu->space->createUrl('/space/space/home');
            foreach ($menu->getEntries() as $entry) {
                if ($entry instanceof MenuLink && $entry->getUrl() === $stream) {
                    $menu->removeEntry($entry);
                }
            }
            Yii::$app->view->registerCss('.panel-profile-controls .statistics { display: none; }');
        } catch (\Throwable $e) {
            Yii::error($e);
        }
    }

    /** Stream des Space (/home) führt direkt zum Kalender */
    public static function onControllerBeforeAction($event)
    {
        try {
            $controller = $event->action->controller;
            if ($controller->uniqueId !== 'space/space' || $event->action->id !== 'home' || Yii::$app->request->isAjax) {
                return;
            }
            $space = $controller->contentContainer ?? null;
            if ($space instanceof Space && self::imAbfallSpace($space)) {
                $event->isValid = false;
                $controller->redirect($space->createUrl('/calendar/view/index'));
            }
        } catch (\Throwable $e) {
            Yii::error($e);
        }
    }
}
