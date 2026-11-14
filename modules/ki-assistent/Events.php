<?php

namespace humhub\modules\kiAssistent;

use Yii;
use humhub\modules\ui\menu\MenuLink;
use yii\helpers\Url;

class Events
{
    /** Täglich: Bewertungen nach Feedback::KEEP_DAYS löschen (Datenschutzerklärung Abschnitt 10) */
    public static function onCronDaily($event)
    {
        try {
            \humhub\modules\kiAssistent\models\Feedback::cleanup();
            components\Uebersetzer::cleanup();
        } catch (\Throwable $e) {
            Yii::error($e);
        }
    }

    public static function onTopMenuInit($event)
    {
        try {
            $event->sender->addEntry(new MenuLink([
                'label' => 'Uffi',
                'url' => Url::to(['/ki-assistent/chat']),
                'icon' => 'comments',
                'isActive' => Yii::$app->controller->module && Yii::$app->controller->module->id === 'ki-assistent'
                    && Yii::$app->controller->id !== 'admin',
                'sortOrder' => 900,
            ]));
        } catch (\Throwable $e) {
            Yii::error($e);
        }
    }

    /** Administration → KI-Anbieter */
    public static function onAdminMenuInit($event)
    {
        try {
            $event->sender->addEntry(new MenuLink([
                'id' => 'ki-anbieter',
                'label' => Yii::t('KiAssistentModule.base', 'KI-Anbieter'),
                'url' => ['/ki-assistent/admin/index'],
                'icon' => 'magic',
                'sortOrder' => 160,
                'isActive' => \humhub\helpers\ControllerHelper::isActivePath('ki-assistent', 'admin'),
                'isVisible' => Yii::$app->user->isAdmin(),
            ]));
        } catch (\Throwable $e) {
            Yii::error($e, 'ki-assistent');
        }
    }

    /** Avatar-Knopf mit Chatfenster unten rechts, nur beim vollen Seitenaufbau (PJAX lässt ihn stehen) */
    public static function onLayoutAddons($event)
    {
        try {
            if (Yii::$app->request->isPjax) {
                return;
            }
            $event->sender->addWidget(widgets\FloatingChat::class);
            // „Übersetzen“ unter Inhalten, wenn die eingestellte Sprache nicht Deutsch ist
            if (components\Uebersetzer::ziel() !== null && Yii::$app->getModule('ki-assistent')->getApiKey() !== null) {
                $event->sender->addWidget(widgets\UebersetzenConfig::class);
            }
        } catch (\Throwable $e) {
            Yii::error($e);
        }
    }
}
