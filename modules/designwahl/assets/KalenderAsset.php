<?php

namespace humhub\modules\designwahl\assets;

use yii\web\AssetBundle;

/** Gestaltung der Teilnahme-Knöpfe an Terminen (beide Designs, angemeldete Nutzer) */
class KalenderAsset extends AssetBundle
{
    public $sourcePath = '@designwahl/resources/kalender';
    public $css = ['kalender.css'];
}
