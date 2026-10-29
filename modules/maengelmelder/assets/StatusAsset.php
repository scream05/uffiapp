<?php

namespace humhub\modules\maengelmelder\assets;

use yii\web\AssetBundle;

/** Gestaltung des Status-Etiketts (Meldung, Bauhof, Karte); titel.js verlinkt die Überschrift einer Meldung */
class StatusAsset extends AssetBundle
{
    public $sourcePath = '@maengelmelder/resources/status';
    public $css = ['status.css'];
    public $js = ['titel.js'];
    public $publishOptions = ['forceCopy' => false];
}
