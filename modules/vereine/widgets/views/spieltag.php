<?php
/**
 * Startseite: heutige Spiele aller Sportarten; solange noch eines aussteht oder läuft, lädt sich der Kasten jede Minute neu
 * @var \humhub\modules\vereine\models\VereinSpiel[] $spiele
 * @var string[] $label
 * @var \humhub\modules\vereine\models\Verein $verein erster Verein (Adresse zum Neuladen)
 * @var \humhub\modules\vereine\models\Verein[] $vereinVon Mannschaft-ID => Verein
 */

use humhub\helpers\Html;
use humhub\modules\vereine\components\Bfv;
use humhub\modules\vereine\components\Spielquellen;

$offen = array_filter($spiele, fn($s) => !$s->istVorbei() && $s->getZustand() === null);
$live = array_filter($spiele, fn($s) => $s->laeuft());
$gestrichen = fn($s) => $s->getZustand() !== null;
$seite = $verein->getSpieleUrl();
$nurFussball = !array_filter($spiele, fn($s) => $s->getQuelle() !== 'bfv');
$mehrereSportarten = count(array_unique(array_map(fn($s) => $s->getQuelle(), $spiele))) > 1;
// Je Sportart ein Link zur Seite (Reihenfolge wie im Menü)
$heuteIds = array_flip(array_map(fn($s) => $s->mannschaft_id, $spiele));
$seiten = [];
foreach ($vereinVon as $mid => $v) {
    if (isset($heuteIds[$mid])) {
        $seiten[$v->id] = $v;
    }
}
// Höchstens so viele Zeilen: laufende und kommende zuerst, dann die jüngsten Ergebnisse; angezeigt nach Uhrzeit
$max = 8;
$weitere = max(0, count($spiele) - $max);
if ($weitere) {
    $offenZuerst = $spiele;
    usort($offenZuerst, fn($a, $b) => [(int)$a->istVorbei(), $a->istVorbei() ? -$a->getZeitpunkt() : $a->getZeitpunkt()]
        <=> [(int)$b->istVorbei(), $b->istVorbei() ? -$b->getZeitpunkt() : $b->getZeitpunkt()]);
    $behalten = array_slice($offenZuerst, 0, $max);
    $spiele = array_values(array_filter($spiele, fn($s) => in_array($s, $behalten, true)));
}
$wertungText = ['s' => Yii::t('VereineModule.base', 'gewonnen'), 'u' => Yii::t('VereineModule.base', 'unentschieden'), 'n' => Yii::t('VereineModule.base', 'verloren')];
?>
<div class="panel panel-default" id="fvs-heute" data-url="<?= Html::encode($seite . (str_contains($seite, '?') ? '&' : '?') . 'teil=heute') ?>" data-aktiv="<?= $offen ? 1 : 0 ?>">
    <style <?= Html::nonce() ?>>
        #fvs-heute .fvh-zeile { display: flex; align-items: center; gap: 8px; padding: 6px 0; border-top: 1px solid var(--bs-border-color, #e5e5e5); color: inherit; text-decoration: none; }
        #fvs-heute .fvh-zeile:first-child { border-top: 0; padding-top: 0; }
        #fvs-heute .fvh-mitte { flex: 1 1 auto; min-width: 0; line-height: 1.3; }
        #fvs-heute .fvh-team { font-weight: 600; }
        #fvs-heute .fvh-gegner { display: block; font-size: .85em; color: var(--bs-secondary-color, #6b6b6b); overflow-wrap: break-word; }
        #fvs-heute .fvh-stand { flex: 0 0 auto; min-width: 48px; text-align: center; font-weight: 700; font-variant-numeric: tabular-nums;
            border-radius: 6px; padding: 3px 7px; background: var(--bs-secondary-bg, #eee); line-height: 1.2; }
        #fvs-heute .fvh-stand small { display: block; font-weight: 400; font-size: .75em; }
        #fvs-heute .fvh-live { background: #c62828; color: #fff; }
        #fvs-heute .fvh-s { background: var(--bs-success-bg-subtle, #d1e7dd); color: var(--bs-success-text-emphasis, #0a3622); }
        #fvs-heute .fvh-n { background: var(--bs-danger-bg-subtle, #f8d7da); color: var(--bs-danger-text-emphasis, #58151c); }
        #fvs-heute .fvh-leise { font-weight: 400; font-size: .85em; color: var(--bs-secondary-color, #6b6b6b); background: transparent; }
        #fvs-heute .fvh-punkt { display: inline-block; width: 9px; height: 9px; border-radius: 50%; background: #c62828; margin-right: 2px; animation: fvh-puls 1.6s ease-in-out infinite; }
        @keyframes fvh-puls { 50% { opacity: .25; } }
        @media (prefers-reduced-motion: reduce) { #fvs-heute .fvh-punkt { animation: none; } }
        #fvs-heute .fvh-abgesetzt { color: var(--bs-danger-text-emphasis, #58151c); }
        #fvs-heute .fvh-weg .fvh-team, #fvs-heute .fvh-weg .fvh-gegner { text-decoration: line-through; opacity: .7; }
        #fvs-heute .fvh-alle { display: flex; flex-wrap: wrap; gap: 2px 12px; margin-top: 8px; }
        #fvs-heute .fvh-weitere { font-size: .85em; color: var(--bs-secondary-color, #6b6b6b); padding-top: 6px; border-top: 1px solid var(--bs-border-color, #e5e5e5); }
        #fvs-heute .fvh-sport { flex: 0 0 auto; width: 16px; text-align: center; color: var(--bs-secondary-color, #6b6b6b); }
    </style>
    <div class="panel-heading">
        <?php if ($live): ?><span class="fvh-punkt" aria-hidden="true"></span><?php else: ?><i class="fa <?= $nurFussball ? 'fa-futbol-o' : 'fa-trophy' ?>"></i><?php endif ?>
        <strong><?= $nurFussball ? ($live ? Yii::t('VereineModule.base', 'Fußball live') : Yii::t('VereineModule.base', 'Fußball heute'))
                : ($live ? Yii::t('VereineModule.base', 'Sport live') : Yii::t('VereineModule.base', 'Sport heute')) ?></strong>
    </div>
    <div class="panel-body">
        <?php foreach ($spiele as $s): ?>
            <?php $stand = $s->getAnzeigeStand(); $wertung = $s->getWertung();
            $v = $vereinVon[$s->mannschaft_id] ?? $verein; $q = Spielquellen::quelle($v);
            // Ohne Heimrecht (Volleyball) steht „gegen …“: dann das eigene Ergebnis zuerst
            if ($stand !== null && $s->getHeimspiel() === null && !$s->heim_eigen) {
                $stand = implode(':', array_reverse(explode(':', $stand)));
            } ?>
            <a class="fvh-zeile<?= $gestrichen($s) ? ' fvh-weg' : '' ?>" href="<?= Html::encode($v->getSpieleUrl() ?: $seite) ?>">
                <?php if ($mehrereSportarten): ?>
                    <i class="fa <?= $q['icon'] ?> fvh-sport" title="<?= Html::encode(Spielquellen::sport($v)) ?>"></i><span class="visually-hidden"><?= Html::encode(Spielquellen::sport($v)) ?>:</span>
                <?php endif ?>
                <span class="fvh-mitte">
                    <span class="fvh-team"><?= Html::encode($label[$s->mannschaft_id] ?? '') ?></span>
                    <span class="fvh-gegner"><?= $s->heim_eigen || $s->getHeimspiel() === null ? Yii::t('VereineModule.base', 'gegen {gegner}', ['gegner' => Html::encode($s->getGegner())])
                            : Yii::t('VereineModule.base', 'bei {gegner}', ['gegner' => Html::encode($s->heim)]) ?></span>
                </span>
                <?php if ($s->laeuft()): ?>
                    <span class="fvh-stand fvh-live"><?= $stand !== null ? Html::encode($stand) : Yii::t('VereineModule.base', 'läuft') ?>
                        <?php if ($s->ticker_minute !== null): ?><small><?= (int)$s->ticker_minute ?>.</small><?php endif ?></span>
                <?php elseif ($gestrichen($s)): ?>
                    <span class="fvh-stand fvh-leise fvh-abgesetzt"><?= Html::encode($s->getZustand()) ?></span>
                <?php elseif ($wertung !== null): ?>
                    <span class="fvh-stand fvh-<?= $wertung ?>" title="<?= Html::encode($wertungText[$wertung]) ?>"><?= Html::encode($stand) ?></span>
                <?php elseif ($s->istVorbei()): ?>
                    <span class="fvh-stand fvh-leise"><?= Yii::t('VereineModule.base', 'Ergebnis folgt') ?></span>
                <?php else: ?>
                    <span class="fvh-stand fvh-leise"><?= $s->uhrzeit ? Html::encode(Bfv::format($s->getZeitpunkt(), 'Hm')) : '–' ?></span>
                <?php endif ?>
            </a>
        <?php endforeach ?>
        <?php if ($weitere): ?>
            <div class="fvh-weitere"><?= Yii::t('VereineModule.base', '+ {n} weitere Spiele heute', ['n' => $weitere]) ?></div>
        <?php endif ?>
        <div class="fvh-alle">
            <?php if (count($seiten) > 1): ?>
                <?php foreach ($seiten as $v): ?>
                    <?= Html::a(Html::encode(Spielquellen::sport($v) . ': ' . ($v->bfv_name ?: $v->name)) . ' <i class="fa fa-angle-right"></i>', $v->getSpieleUrl()) ?>
                <?php endforeach ?>
            <?php else: ?>
                <?= Html::a(Yii::t('VereineModule.base', 'Spiele & Ergebnisse') . ' <i class="fa fa-angle-right"></i>', $seite) ?>
            <?php endif ?>
        </div>
    </div>
</div>
<?php if ($offen && Yii::$app->request instanceof \yii\web\Request && !Yii::$app->request->isAjax) {
    $this->registerJs(<<<JS
(function () {
    if (window.fvsHeuteTimer) { return; } // läuft schon (PJAX), findet den neuen Kasten selbst
    function laden() {
        var box = document.getElementById('fvs-heute');
        if (!box || box.dataset.aktiv !== '1') { clearInterval(window.fvsHeuteTimer); window.fvsHeuteTimer = null; return; }
        if (document.hidden) { return; }
        fetch(box.dataset.url, {credentials: 'same-origin', headers: {'X-Requested-With': 'XMLHttpRequest'}})
            .then(function (r) { return r.ok ? r.text() : null; })
            .then(function (html) {
                if (html === null || !document.body.contains(box)) { return; }
                if (html.trim() === '') { box.remove(); return; }
                box.outerHTML = html;
            }).catch(function () {});
    }
    window.fvsHeuteTimer = setInterval(laden, 60000);
    document.addEventListener('visibilitychange', function () { if (!document.hidden && window.fvsHeuteTimer) { laden(); } });
})();
JS);
} ?>
