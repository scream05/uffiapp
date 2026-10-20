<?php

namespace humhub\modules\statistik;

use humhub\helpers\ControllerHelper;
use humhub\modules\statistik\components\Erfassung;
use humhub\modules\ui\menu\MenuLink;
use Yii;

class Events
{
    /** Nach jeder Web-Anfrage: echte Seitenaufrufe (ganze Seite oder Pjax-Wechsel) zählen */
    public static function onAfterRequest($event)
    {
        try {
            Erfassung::erfassen();
        } catch (\Throwable $e) {
            Yii::error($e, 'statistik');
        }
    }

    /** Administration: Menüpunkt „Statistik“ (nur Admins) */
    public static function onAdminMenuInit($event)
    {
        try {
            $event->sender->addEntry(new MenuLink([
                'id' => 'statistik',
                'label' => Yii::t('StatistikModule.base', 'Statistik'),
                'url' => ['/statistik/admin/index'],
                'icon' => 'bar-chart',
                'sortOrder' => 150,
                'isActive' => ControllerHelper::isActivePath('statistik', 'admin'),
                'isVisible' => Yii::$app->user->isAdmin(),
            ]));
        } catch (\Throwable $e) {
            Yii::error($e, 'statistik');
        }
    }

    /** Täglich: Rohdaten nach Ablauf der Aufbewahrung löschen */
    public static function onCronDaily($event)
    {
        try {
            $monate = Yii::$app->getModule('statistik')->aufbewahrungMonate;
            Yii::$app->db->createCommand()->delete('statistik_aufruf',
                ['<', 'tag', date('Y-m-d', strtotime("-$monate months"))])->execute();
        } catch (\Throwable $e) {
            Yii::error($e, 'statistik');
        }
    }
}
