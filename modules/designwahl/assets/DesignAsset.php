<?php

namespace humhub\modules\designwahl\assets;

use yii\web\AssetBundle;

/** Design „Uffi frisch“: greift nur unter html.design-neu. Nach Änderungen: touch modules/designwahl/resources */
class DesignAsset extends AssetBundle
{
    public $sourcePath = '@designwahl/resources';
    public $css = ['neu.css'];
    public $js = ['neu.js'];
    public $depends = ['humhub\assets\AppAsset'];
}
