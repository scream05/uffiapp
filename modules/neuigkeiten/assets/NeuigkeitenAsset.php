<?php

namespace humhub\modules\neuigkeiten\assets;

use yii\web\AssetBundle;

class NeuigkeitenAsset extends AssetBundle
{
    public $sourcePath = '@neuigkeiten/resources';
    public $css = ['neuigkeiten.css'];
    public $js = ['neuigkeiten.js'];
    public $publishOptions = ['forceCopy' => false];
}
