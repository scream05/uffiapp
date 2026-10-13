<?php

namespace humhub\modules\designwahl\assets;

use yii\web\AssetBundle;

/** Einfache Textfelder statt Richtext-Editor (Nachrichten), für angemeldete Nutzer in beiden Designs */
class TextfeldAsset extends AssetBundle
{
    public $sourcePath = '@designwahl/resources';
    public $js = ['textfeld.js'];
    public $depends = ['humhub\assets\AppAsset'];
}
