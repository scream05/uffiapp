<?php

namespace humhub\modules\eilmeldung;

use humhub\modules\eilmeldung\widgets\Hinweis;
use humhub\modules\ui\menu\MenuLink;
use Yii;
use yii\helpers\Url;

class Events
{
    /**
     * Startseite (auch für Gäste): laufende Eilmeldungen ganz oben über dem Stream. Vor dem Lauf ausgeben –
     * DashboardContent gibt den Stream direkt aus, ein an das Ergebnis gehängter Kasten stünde darunter.
     */
    public static function onDashboardContentBeforeRun($event)
    {
        try {
            echo Hinweis::widget();
        } catch (\Throwable $e) {
            Yii::error($e);
        }
    }

    /** Konto → Eilmeldungen schreiben (nur Admins und Bauhof) */
    public static function onAccountMenuInit($event)
    {
        try {
            if (!Yii::$app->getModule('eilmeldung')->darfSchreiben(Yii::$app->user->getIdentity())) {
                return;
            }
            $event->sender->addEntry(new MenuLink([
                'label' => Yii::t('EilmeldungModule.base', 'Eilmeldungen'),
                'url' => Url::to(['/eilmeldung/verwalten']),
                'icon' => 'exclamation-triangle',
                'sortOrder' => 460,
                'isActive' => Yii::$app->controller->module && Yii::$app->controller->module->id === 'eilmeldung',
            ]));
        } catch (\Throwable $e) {
            Yii::error($e);
        }
    }
}
