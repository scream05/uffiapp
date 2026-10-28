<?php

namespace humhub\modules\designwahl\assets;

use yii\web\AssetBundle;

/** Web-App am iPhone: Tab-Leiste und Uffi-Knopf zurück an den unteren Rand, wenn iOS den Bildschirm zu kurz meldet */
class ViewportAsset extends AssetBundle
{
    public $sourcePath = '@designwahl/resources/viewport';
    public $js = ['viewport.js'];
    public $depends = ['humhub\assets\AppAsset'];
}
