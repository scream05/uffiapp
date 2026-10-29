<?php

namespace humhub\modules\neuigkeiten;

use humhub\modules\neuigkeiten\widgets\Popup;
use humhub\modules\neuigkeiten\widgets\Rundgang;
use humhub\modules\ui\menu\MenuLink;
use Yii;

class Events
{
    public static function onDashboardSidebarInit($event)
    {
        try {
            if (!Yii::$app->user->isGuest) {
                // Erst der Willkommensrundgang (einmal, oder auf Wunsch per ?rundgang=1), danach die Neuigkeiten
                $module = Yii::$app->getModule('neuigkeiten');
                $rundgang = Yii::$app->request->get('rundgang') || $module->rundgangOffen(Yii::$app->user->getIdentity());
                $event->sender->addWidget($rundgang ? Rundgang::class : Popup::class, [], ['sortOrder' => 1]);
            }
        } catch (\Throwable $e) {
            Yii::error($e, 'neuigkeiten');
        }
    }

    /** Kurze Adressen /hilfe/app, /hilfe/regeln und /hilfe/barrierefreiheit */
    public static function onBeforeRequest($event)
    {
        try {
            Yii::$app->urlManager->addRules([
                'hilfe' => 'neuigkeiten/hilfe/index',
                'hilfe/<seite:(app|regeln|barrierefreiheit)>' => 'neuigkeiten/hilfe/seite',
            ], false);
        } catch (\Throwable $e) {
            Yii::error($e, 'neuigkeiten');
        }
    }

    /** Fußzeile: Links „Hilfe & Regeln“, „Barrierefreiheit“ und „Neuigkeiten“ (vor „Ideen & Vorschläge“ und „Quellcode“) */
    public static function onFooterMenuInit($event)
    {
        try {
            $event->sender->addEntry(new MenuLink([
                'label' => Yii::t('NeuigkeitenModule.base', 'Neuigkeiten'),
                'url' => \yii\helpers\Url::to(['/neuigkeiten/index']),
                'sortOrder' => 880,
            ]));
            $event->sender->addEntry(new MenuLink([
                'label' => Yii::t('NeuigkeitenModule.base', 'Hilfe & Regeln'),
                'url' => \yii\helpers\Url::to(['/neuigkeiten/hilfe/seite', 'seite' => 'app']),
                'sortOrder' => 875,
            ]));
            $event->sender->addEntry(new MenuLink([
                'label' => Yii::t('NeuigkeitenModule.base', 'Barrierefreiheit'),
                'url' => \yii\helpers\Url::to(['/neuigkeiten/hilfe/seite', 'seite' => 'barrierefreiheit']),
                'sortOrder' => 877,
            ]));
        } catch (\Throwable $e) {
            Yii::error($e, 'neuigkeiten');
        }
    }
}
