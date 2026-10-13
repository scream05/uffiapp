<?php

namespace humhub\modules\maengelmelder\assets;

use yii\web\AssetBundle;

/** Gestaltung des Status-Etiketts (Meldung, Bauhof, Karte) */
class StatusAsset extends AssetBundle
{
    public $sourcePath = '@maengelmelder/resources/status';
    public $css = ['status.css'];
    public $publishOptions = ['forceCopy' => false];
}
