<?php
/**
 * Bestätigung zum Vorzeigen an der Kasse. Bewegter Hintergrund und laufende Uhr zeigen dem Personal,
 * dass es kein Bildschirmfoto ist.
 * @var \humhub\modules\gewerbe\models\Einloesung $e
 * @var bool $live noch innerhalb der Anzeigezeit und nicht zurückgenommen
 * @var int $rest Sekunden, die die Live-Ansicht noch läuft
 */

use humhub\helpers\Html;

$space = $this->context->contentContainer;
$g = $e->gutschein;
$f = Yii::$app->formatter;
?>
<div class="gs-best<?= $live ? ' live' : '' ?>" role="status">
    <?php if ($live): ?>
        <div class="gs-best-haken" aria-hidden="true"><i class="fa fa-check"></i></div>
        <div class="gs-best-label">Gutschein eingelöst</div>
    <?php else: ?>
        <div class="gs-best-label"><?= $e->storniert_am ? 'Vom Betrieb zurückgenommen' : 'Abgelaufen – nicht mehr vorzeigbar' ?></div>
    <?php endif ?>
    <div class="gs-best-titel"><?= Html::encode($g->titel) ?></div>
    <div class="gs-best-betrieb"><?= Html::encode($e->betrieb->name) ?></div>
    <div class="gs-best-code" aria-label="Code <?= Html::encode(implode(' ', str_split($e->code))) ?>"><?= Html::encode($e->code) ?></div>
    <div class="gs-best-zeit">Eingelöst <?= $f->asDate($e->created_at, 'php:j.n.Y') ?> um <?= $f->asTime($e->created_at, 'php:H:i') ?> Uhr</div>
    <?php if ($live): ?>
        <div class="gs-best-uhr" id="gs-uhr" aria-live="off">--:--:--</div>
        <div class="gs-best-rest" id="gs-rest"></div>
    <?php endif ?>
</div>
<p style="text-align:center;margin-top:14px">
    <?= Html::a('Zum Gutschein', $g->getUrl(), ['class' => 'btn btn-light']) ?>
</p>
<style <?= Html::nonce() ?>>
    .gs-best { margin: 0 auto; max-width: 520px; padding: 28px 20px 24px; border-radius: 22px; text-align: center; color: #fff;
        background: #6b6f73; box-shadow: 0 8px 28px rgba(0, 0, 0, .18); }
    .gs-best.live { background: linear-gradient(120deg, #446e88, #5e7f0a, #446e88, #2f5a73); background-size: 300% 300%;
        animation: gs-wolke 6s ease-in-out infinite; }
    @keyframes gs-wolke { 0% { background-position: 0 50%; } 50% { background-position: 100% 50%; } 100% { background-position: 0 50%; } }
    .gs-best-haken { width: 84px; height: 84px; margin: 0 auto 10px; border-radius: 50%; background: #fff; color: #5e7f0a;
        display: flex; align-items: center; justify-content: center; font-size: 46px; animation: gs-puls 1.6s ease-in-out infinite; }
    @keyframes gs-puls { 0%, 100% { box-shadow: 0 0 0 0 rgba(255, 255, 255, .7); } 50% { box-shadow: 0 0 0 16px rgba(255, 255, 255, 0); } }
    .gs-best-label { font-size: 1.05em; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; opacity: .95; }
    .gs-best-titel { font-size: 1.7em; font-weight: 800; line-height: 1.2; margin: 12px 0 4px; overflow-wrap: anywhere; }
    .gs-best-betrieb { font-size: 1.1em; opacity: .95; }
    .gs-best-code { display: inline-block; margin: 16px 0 8px; padding: 6px 18px; border-radius: 12px; background: rgba(255, 255, 255, .95);
        color: #1d2b33; font: 800 2.2em/1.1 ui-monospace, Menlo, Consolas, monospace; letter-spacing: .18em; }
    .gs-best-zeit { opacity: .95; }
    .gs-best-uhr { margin-top: 14px; font: 700 2.6em/1 ui-monospace, Menlo, Consolas, monospace; font-variant-numeric: tabular-nums; }
    .gs-best-rest { margin-top: 6px; font-size: .9em; opacity: .9; }
    @media (prefers-reduced-motion: reduce) {
        .gs-best.live { animation-duration: 20s; }
        .gs-best-haken { animation: none; }
    }
</style>
<?php if ($live): ?>
<script <?= Html::nonce() ?>>
(function () {
    // Bei PJAX-Wechseln nur eine Uhr laufen lassen
    if (window.gsUhr) clearInterval(window.gsUhr);
    var ende = Date.now() + <?= (int)$rest ?> * 1000;
    function zwei(n) { return (n < 10 ? '0' : '') + n; }
    function tick() {
        var uhr = document.getElementById('gs-uhr');
        if (!uhr) { clearInterval(window.gsUhr); return; }
        var d = new Date();
        uhr.textContent = zwei(d.getHours()) + ':' + zwei(d.getMinutes()) + ':' + zwei(d.getSeconds());
        var rest = Math.max(0, Math.round((ende - Date.now()) / 1000));
        document.getElementById('gs-rest').textContent = rest > 0
            ? 'Noch ' + Math.floor(rest / 60) + ':' + zwei(rest % 60) + ' Min. zum Vorzeigen'
            : 'Zeit abgelaufen';
        if (rest === 0) { clearInterval(window.gsUhr); location.reload(); }
    }
    tick();
    window.gsUhr = setInterval(tick, 1000);
})();
</script>
<?php endif ?>
