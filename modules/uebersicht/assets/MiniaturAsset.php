<?php

namespace humhub\modules\uebersicht\assets;

use yii\web\AssetBundle;

/** Miniaturbild links im Beitrag, Text läuft herum (alle Streams). Nach Änderungen: touch modules/uebersicht/resources */
class MiniaturAsset extends AssetBundle
{
    public $sourcePath = '@uebersicht/resources';
    public $css = ['miniatur.css'];
    public $js = ['miniatur.js'];
    public $depends = ['humhub\assets\AppAsset'];
}
