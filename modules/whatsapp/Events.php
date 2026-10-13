<?php

namespace humhub\modules\whatsapp;

use humhub\modules\whatsapp\models\Entwurf;
use Yii;

class Events
{
    /** Menü „WhatsApp-Eingang (n)“ im Space „Veranstaltungen“, nur für Admins */
    public static function onSpaceMenuInit($event)
    {
        try {
            $space = $event->sender->space;
            if (!$space->moduleManager->isEnabled('whatsapp') || !Module::canReview()) {
                return;
            }
            $open = Entwurf::find()->where(['status' => Entwurf::STATUS_OFFEN])->count();
            $event->sender->addItem([
                'label' => 'WhatsApp-Eingang' . ($open ? " ($open)" : ''),
                'group' => 'modules',
                'url' => $space->createUrl('/whatsapp/eingang/index'),
                'icon' => '<i class="fa fa-whatsapp"></i>',
                'isActive' => Yii::$app->controller->module && Yii::$app->controller->module->id === 'whatsapp',
                'sortOrder' => 95,
            ]);
        } catch (\Throwable $e) {
            Yii::error($e);
        }
    }
}
