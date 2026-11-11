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
     * Manifest der Web-App: Start vom Startbildschirm mit Kennzeichen öffnen, damit die Statistik diese Starts zählen kann.
     * Direkt /dashboard statt / – die Weiterleitung von / würde das Kennzeichen verlieren.
     */
    public static function onManifest($event)
    {
        try {
            $res = $event->result;
            if ($res instanceof \yii\web\Response && is_array($res->data) && isset($res->data['start_url'])) {
                // id und scope fest auf „/“: so bleibt die installierte App dieselbe wie vorher (ohne id wäre es die start_url)
                $res->data['id'] = '/';
                $res->data['scope'] = '/';
                $res->data['start_url'] = '/dashboard?quelle=' . Module::START_QUELLE;
            }
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
