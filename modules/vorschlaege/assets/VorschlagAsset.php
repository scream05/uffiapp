<?php

namespace humhub\modules\vorschlaege\assets;

use yii\web\AssetBundle;

/** Gestaltung und Abstimmen ohne Neuladen (Stand-Etikett, Knöpfe, Übersichtsseite) */
class VorschlagAsset extends AssetBundle
{
    public $sourcePath = '@vorschlaege/resources';
    public $css = ['vorschlag.css'];
    public $js = ['vorschlag.js'];
    public $depends = [\yii\web\JqueryAsset::class];
    public $publishOptions = ['forceCopy' => false];
}
