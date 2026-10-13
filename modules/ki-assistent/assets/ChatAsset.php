<?php

namespace humhub\modules\kiAssistent\assets;

use yii\web\AssetBundle;

/** Chat-Logik und -Aussehen, gemeinsam für Chat-Seite und Chatfenster */
class ChatAsset extends AssetBundle
{
    public $sourcePath = '@ki-assistent/resources';
    public $css = ['ki-chat.css'];
    public $js = ['ki-chat.js'];
    public $depends = ['humhub\assets\AppAsset'];
}
