<?php

namespace humhub\modules\kiAssistent;

use Yii;
use yii\helpers\Url;

class Events
{
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
        } catch (\Throwable $e) {
            Yii::error($e);
        }
    }
}
