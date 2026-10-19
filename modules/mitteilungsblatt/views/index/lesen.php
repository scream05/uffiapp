<?php

use humhub\helpers\Html;
use humhub\modules\mitteilungsblatt\assets\PdfjsAsset;
use yii\helpers\Url;

/** @var \humhub\modules\mitteilungsblatt\models\Ausgabe $ausgabe */
/** @var int|null $seite */
/** @var string|null $q */
/** @var array $zurueck [Beschriftung, Adresse] des Zurück-Knopfs */

$this->title = Yii::t('MitteilungsblattModule.base', 'Mitteilungsblatt {titel}', ['titel' => $ausgabe->titelAnzeige]);
$hash = array_filter(['page' => $seite, 'search' => $q]);
$viewer = PdfjsAsset::register($this)->baseUrl . '/web/viewer.html?file='
    . rawurlencode($this->context->url('/mitteilungsblatt/index/pdf', ['id' => $ausgabe->id]))
    . ($hash ? '#' . http_build_query($hash, '', '&', PHP_QUERY_RFC3986) : '');
// Sprache der Bedienelemente von pdf.js (pdfjs/web/locale/<lang>)
$pdfLang = in_array(Yii::$app->language, ['de', 'en-US', 'uk', 'ar', 'tr', 'ru'], true) ? Yii::$app->language : 'de';
?>
<style <?= Html::nonce() ?>>
    .mb-kopf { display: flex; flex-wrap: wrap; align-items: center; gap: 8px 16px; margin-bottom: 10px; }
    .mb-kopf h1 { font-size: 1.25rem; margin: 0; flex: 1 1 auto; }
    .mb-kopf h1 small { font-size: .8em; font-weight: normal; color: var(--hh-text-color-soft, #777); }
    .mb-kopf .btn { white-space: nowrap; }
    .panel-body > p:last-child { margin-bottom: 0; }
    #mb-reader { position: relative; height: 70vh; min-height: 360px; border-radius: 8px; overflow: hidden;
        box-shadow: 0 0 0 1px var(--hh-border-color, #ddd); background: #525659; }
    #mb-reader iframe { width: 100%; height: 100%; border: 0; display: block; }
    #mb-reader .mb-schliessen { display: none; }
    #mb-reader.mb-vollbild { position: fixed; inset: 0; z-index: 2000; height: auto !important; border-radius: 0;
        padding-top: env(safe-area-inset-top); padding-bottom: env(safe-area-inset-bottom); }
    #mb-reader.mb-vollbild .mb-schliessen { display: block; position: absolute; right: 10px; bottom: calc(14px + env(safe-area-inset-bottom));
        z-index: 1; border-radius: 999px; box-shadow: 0 4px 14px rgba(0, 0, 0, .35); }
    body.mb-vollbild-offen { overflow: hidden; }
    @media (max-width: 575.98px) { .mb-kopf .mb-text { display: none; } }
</style>
<div class="<?= $this->context->contentContainer ? 'panel panel-default' : 'container' ?>"><div class="<?= $this->context->contentContainer ? 'panel-body' : '' ?>">
    <div class="mb-kopf">
        <?= Html::a('<i class="fa fa-arrow-left"></i><span class="mb-text"> ' . Html::encode($zurueck[0]) . '</span>', $zurueck[1],
            ['class' => 'btn btn-light', 'title' => $zurueck[0]]) ?>
        <h1><?= Html::encode(Yii::t('MitteilungsblattModule.base', 'Mitteilungsblatt {titel}', ['titel' => $ausgabe->titelAnzeige])) ?> <small><?= Html::encode($ausgabe->datumAnzeige) ?></small></h1>
        <button type="button" class="btn btn-light" id="mb-vollbild" title="<?= Html::encode(Yii::t('MitteilungsblattModule.base', 'Vollbild')) ?>">
            <i class="fa fa-expand"></i><span class="mb-text"> <?= Yii::t('MitteilungsblattModule.base', 'Vollbild') ?></span></button>
        <?= Html::a('<i class="fa fa-download"></i><span class="mb-text"> ' . Yii::t('MitteilungsblattModule.base', 'Herunterladen') . '</span>',
            $this->context->url('/mitteilungsblatt/index/pdf', ['id' => $ausgabe->id, 'download' => 1]),
            ['class' => 'btn btn-light', 'title' => Yii::t('MitteilungsblattModule.base', 'Herunterladen'), 'data-pjax-prevent' => 1]) ?>
    </div>
    <div id="mb-reader" data-src="<?= Html::encode($viewer) ?>" data-lang="<?= Html::encode($pdfLang) ?>">
        <iframe title="<?= Html::encode(Yii::t('MitteilungsblattModule.base', 'Mitteilungsblatt {titel}', ['titel' => $ausgabe->titelAnzeige])) ?>" allow="fullscreen"></iframe>
        <button type="button" class="btn btn-primary mb-schliessen"><i class="fa fa-compress"></i> <?= Yii::t('MitteilungsblattModule.base', 'Schließen') ?></button>
    </div>
    <p class="text-muted" style="margin-top:8px;font-size:.85em">
        Herausgeberin: Stadt Uffenheim. <?= Html::a('Original auf uffenheim.de', $ausgabe->quelle_url, ['target' => '_blank', 'rel' => 'noopener']) ?>
    </p>
</div></div>
<script <?= Html::nonce() ?>>
(function () {
    var reader = document.getElementById('mb-reader');
    var frame = reader.querySelector('iframe');

    // pdf.js meldet sich vor dem Start beim umgebenden Fenster: hier die Einstellungen setzen
    if (!window.mbViewerHook) {
        window.mbViewerHook = true;
        document.addEventListener('webviewerloaded', function (e) {
            var w = e.detail && e.detail.source;
            if (!w || !w.PDFViewerApplicationOptions) return;
            var o = w.PDFViewerApplicationOptions;
            o.set('disablePreferences', true);
            var r = document.getElementById('mb-reader');
            o.set('localeProperties', {lang: (r && r.dataset.lang) || 'de'});
            o.set('annotationEditorMode', -1); // nur lesen, keine Zeichen-/Notizwerkzeuge
            o.set('enableSignatureEditor', false);
            o.set('enableComment', false);
            o.set('disableHistory', true); // sonst landet jedes Blättern im Zurück-Verlauf des Browsers
            o.set('sidebarViewOnLoad', 0);
            var css = w.document.createElement('style');
            css.textContent = '#secondaryOpenFile,#viewBookmark,#editorModeButtons,#editorModeSeparator{display:none!important}';
            w.document.head.appendChild(css);
        });
    }
    frame.src = reader.dataset.src;

    // Reader bis knapp über den unteren Rand (bzw. die Tab-Leiste am Handy)
    function hoehe() {
        if (!document.body.contains(reader)) {
            window.removeEventListener('resize', hoehe);
            return;
        }
        var unten = parseFloat(getComputedStyle(document.body).paddingBottom) || 0;
        var h = window.innerHeight - reader.getBoundingClientRect().top - unten - 12;
        reader.style.height = Math.max(360, h) + 'px';
    }
    hoehe();
    window.addEventListener('resize', hoehe);

    function vollbild(an) {
        reader.classList.toggle('mb-vollbild', an);
        document.body.classList.toggle('mb-vollbild-offen', an);
    }
    document.getElementById('mb-vollbild').addEventListener('click', function () { vollbild(true); });
    reader.querySelector('.mb-schliessen').addEventListener('click', function () { vollbild(false); });
})();
</script>
