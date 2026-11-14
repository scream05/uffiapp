<?php

namespace humhub\modules\statistik;

use humhub\helpers\ControllerHelper;
use humhub\modules\statistik\components\Erfassung;
use humhub\modules\statistik\Module;
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

    /**
     * Start vom Startbildschirm zählen, ohne das Manifest anzufassen (eine veränderte start_url bricht die Anmeldung
     * über die offizielle HumHub-App). Stattdessen meldet der Client einen Standalone-Start einmal je App-Sitzung per
     * Beacon an /statistik/track/start; dort wird der laufende Besuch als Start markiert (Erfassung::markiereStart).
     */
    public static function onLayoutAddons($event)
    {
        try {
            if (Yii::$app->request->isPjax || Yii::$app->request->isAjax) {
                return;
            }
            $url = \yii\helpers\Url::to(['/statistik/track/start']);
            $js = <<<JS
(function () {
    try {
        var standalone = (window.matchMedia && matchMedia('(display-mode: standalone)').matches) || window.navigator.standalone === true;
        if (!standalone || sessionStorage.getItem('statistikStart')) { return; }
        sessionStorage.setItem('statistikStart', '1');
        if (navigator.sendBeacon) { navigator.sendBeacon('$url'); }
        else { fetch('$url', {method: 'POST', keepalive: true, credentials: 'same-origin'}); }
    } catch (e) {}
})();
JS;
            Yii::$app->view->registerJs($js, \yii\web\View::POS_END, 'statistik-start');
        } catch (\Throwable $e) {
            Yii::error($e, 'statistik');
        }
    }

    /** Kontolöschung: Tage, an denen das Konto aktiv war, sofort löschen (der Fremdschlüssel greift erst beim endgültigen Löschen) */
    public static function onUserLoeschen($event)
    {
        try {
            $user = $event->user ?? $event->sender;
            Yii::$app->db->createCommand()->delete('statistik_konto_tag', ['user_id' => $user->id])->execute();
        } catch (\Throwable $e) {
            Yii::error($e, 'statistik');
        }
    }

    /** Täglich: Rohdaten nach Ablauf der Aufbewahrung löschen */
    public static function onCronDaily($event)
    {
        try {
            $monate = Yii::$app->getModule('statistik')->aufbewahrungMonate;
            $grenze = date('Y-m-d', strtotime("-$monate months"));
            Yii::$app->db->createCommand()->delete('statistik_aufruf', ['<', 'tag', $grenze])->execute();
            Yii::$app->db->createCommand()->delete('statistik_konto_tag', ['<', 'tag', $grenze])->execute();
        } catch (\Throwable $e) {
            Yii::error($e, 'statistik');
        }
    }
}
