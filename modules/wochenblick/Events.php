<?php

namespace humhub\modules\wochenblick;

use humhub\modules\ui\menu\MenuLink;
use Yii;
use yii\helpers\Url;

class Events
{
    /** Konto → Wochenüberblick (nur für freigegebene Konten, siehe Module::$freigabe) */
    public static function onAccountMenuInit($event)
    {
        try {
            if (!Yii::$app->getModule('wochenblick')->istFreigegeben(Yii::$app->user->getIdentity())) {
                return;
            }
            $event->sender->addEntry(new MenuLink([
                'label' => Yii::t('WochenblickModule.base', 'Wochenüberblick'),
                'url' => Url::to(['/wochenblick/einstellungen']),
                'icon' => 'newspaper-o',
                'sortOrder' => 455,
                'isActive' => Yii::$app->controller->module && Yii::$app->controller->module->id === 'wochenblick',
            ]));
        } catch (\Throwable $e) {
            Yii::error($e);
        }
    }
}
