<?php

namespace humhub\modules\designwahl\assets;

use yii\web\AssetBundle;

/** Kalender: Liste ab heute (am Handy Standard), mehrtägige Termine nur einmal – nach dem Kalendermodul laden */
class KalenderListeAsset extends AssetBundle
{
    public $sourcePath = '@designwahl/resources/kalender';
    public $js = ['kalender-liste.js'];
    public $depends = ['humhub\modules\calendar\assets\CalendarAsset'];
    // Die Skripte des Kalendermoduls laden mit defer – sonst liefe dieses vor ihnen
    public $jsOptions = ['defer' => 'defer'];
}
