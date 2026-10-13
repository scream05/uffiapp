<?php

namespace humhub\modules\mitteilungsblatt\assets;

use yii\web\AssetBundle;

/** pdf.js (Viewer) als Asset veröffentlichen – Inhalt holt scripts/run-setup.sh pdfjs */
class PdfjsAsset extends AssetBundle
{
    public $sourcePath = '@mitteilungsblatt/pdfjs';
    public $publishOptions = ['except' => ['*.map']];
}
