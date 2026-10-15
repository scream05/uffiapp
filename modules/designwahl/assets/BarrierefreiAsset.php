<?php

namespace humhub\modules\designwahl\assets;

use yii\web\AssetBundle;

/** Barrierefreiheit (WCAG 2.1 AA): Kontraste, Fokus, Klickflächen, Sprunglink, Namen – beide Designs, auch für Gäste */
class BarrierefreiAsset extends AssetBundle
{
    public $sourcePath = '@designwahl/resources';
    public $css = ['barrierefrei.css'];
    public $js = ['barrierefrei.js'];
    public $depends = ['humhub\assets\AppAsset'];
}
