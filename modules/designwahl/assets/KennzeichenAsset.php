<?php

namespace humhub\modules\designwahl\assets;

use yii\web\AssetBundle;

/** Zahl am Symbol der Web-App bei geöffneter Seite nachführen (angemeldete Besucher) */
class KennzeichenAsset extends AssetBundle
{
    public $sourcePath = '@designwahl/resources/kennzeichen';
    public $js = ['kennzeichen.js'];
    public $depends = ['humhub\assets\AppAsset'];
}
