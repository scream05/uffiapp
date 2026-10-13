<?php

namespace humhub\modules\maengelmelder\assets;

use yii\web\AssetBundle;

/** Leaflet 1.9.4 (BSD-2-Clause), lokal ausgeliefert statt per CDN */
class MapAsset extends AssetBundle
{
    public $sourcePath = '@maengelmelder/resources';
    public $css = ['leaflet/leaflet.css'];
    public $js = ['leaflet/leaflet.js'];
    public $publishOptions = ['forceCopy' => false];
}
