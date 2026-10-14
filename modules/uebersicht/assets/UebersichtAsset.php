<?php

namespace humhub\modules\uebersicht\assets;

use yii\web\AssetBundle;

/** Nach Änderungen: touch modules/uebersicht/resources */
class UebersichtAsset extends AssetBundle
{
    public $sourcePath = '@uebersicht/resources';
    public $css = ['uebersicht.css'];
    public $js = ['uebersicht.js'];
    public $depends = ['humhub\assets\AppAsset'];
}
