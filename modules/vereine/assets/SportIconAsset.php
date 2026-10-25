<?php

namespace humhub\modules\vereine\assets;

use yii\web\AssetBundle;

/** Eigene Sportsymbole (Volleyball, Badminton) als CSS-Klassen. Nach Änderungen: touch modules/vereine/resources */
class SportIconAsset extends AssetBundle
{
    public $sourcePath = '@vereine/resources';
    public $css = ['sport-icons.css'];
}
