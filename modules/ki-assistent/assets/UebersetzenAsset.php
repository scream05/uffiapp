<?php

namespace humhub\modules\kiAssistent\assets;

use yii\web\AssetBundle;

/** „Übersetzen“ unter Inhalten (nur bei anderer Sprache als Deutsch) */
class UebersetzenAsset extends AssetBundle
{
    public $sourcePath = '@ki-assistent/resources';
    public $css = ['uebersetzen.css'];
    public $js = ['uebersetzen.js'];
    public $depends = ['humhub\assets\AppAsset'];
}
