<?php

namespace humhub\modules\whatsapp;

use humhub\modules\whatsapp\models\Entwurf;
use humhub\modules\ui\menu\MenuLink;
use Yii;

class Events
{
    /** Menü „Termin-Eingang (n)“ (WhatsApp und uffenheim.de) im Space „Veranstaltungen“, nur für Admins */
    public static function onSpaceMenuInit($event)
    {
        try {
            $space = $event->sender->space;
            if (!$space->moduleManager->isEnabled('whatsapp') || !Module::canReview()) {
                return;
            }
            $open = Entwurf::find()->where(['status' => Entwurf::STATUS_OFFEN])->count();
            $event->sender->addEntry(new MenuLink([
                'label' => 'Termin-Eingang' . ($open ? " ($open)" : ''),
                'url' => $space->createUrl('/whatsapp/eingang/index'),
                'icon' => 'inbox',
                'isActive' => Yii::$app->controller->module && Yii::$app->controller->module->id === 'whatsapp',
                'sortOrder' => 95,
            ]));
        } catch (\Throwable $e) {
            Yii::error($e);
        }
    }
}
