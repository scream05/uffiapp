<?php

namespace humhub\modules\designwahl\assets;

use yii\web\AssetBundle;

/** Seite nach abgelaufener Sitzung neu laden, sonst bleibt sie in der App leer (alle Besucher) */
class AufwachenAsset extends AssetBundle
{
    public $sourcePath = '@designwahl/resources/aufwachen';
    public $js = ['aufwachen.js'];
    public $depends = ['humhub\assets\AppAsset'];
}
