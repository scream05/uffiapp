<?php

namespace humhub\modules\kiAssistent;

use Yii;
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
            $event->sender->addItem([
                'label' => 'Uffi',
                'url' => Url::to(['/ki-assistent/chat']),
                'icon' => '<i class="fa fa-comments"></i>',
                'isActive' => Yii::$app->controller->module && Yii::$app->controller->module->id === 'ki-assistent',
                'sortOrder' => 900,
            ]);
        } catch (\Throwable $e) {
            Yii::error($e);
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
