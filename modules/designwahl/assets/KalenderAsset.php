<?php

namespace humhub\modules\designwahl\assets;

use yii\web\AssetBundle;

/** Gestaltung der Teilnahme-Knöpfe an Terminen + Sprung zum heutigen Tag im Kalender (beide Designs, alle Besucher) */
class KalenderAsset extends AssetBundle
{
    public $sourcePath = '@designwahl/resources/kalender';
    public $css = ['kalender.css'];
    public $js = ['kalender.js'];
}
