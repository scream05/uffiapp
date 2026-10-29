<?php

use humhub\helpers\Html;
use humhub\modules\tanken\components\Spritpreise;

/** @var \humhub\modules\tanken\Module $module */
/** @var array|null $daten */
/** @var string $sorte */
/** @var int $umkreis */
/** @var bool $nachPreis */
/** @var array $liste */
/** @var array|null $verlauf */

$t = fn($s, $p = []) => Yii::t('TankenModule.base', $s, $p);
$this->title = $t('Spritpreise');
$link = fn($label, $param, $aktiv) => Html::a($label, ['/tanken/index'] + array_merge(['sorte' => $sorte, 'umkreis' => $umkreis, 'nach' => $nachPreis ? 'preis' : 'km'], $param),
    ['class' => 'btn btn-sm ' . ($aktiv ? 'btn-primary' : 'btn-default')]);
$tiefst = Spritpreise::tiefst($liste, $sorte);
?>
<style <?= Html::nonce() ?>>
    .tk-filter { display: flex; flex-wrap: wrap; gap: 8px 16px; margin-bottom: 12px; }
    .tk-filter .btn-group { display: flex; flex-wrap: wrap; }
    .tk-st { display: flex; gap: 12px; align-items: flex-start; padding: 10px 0; border-top: 1px solid var(--hh-border-color, #e5e5e5); }
    .tk-st:first-child { border-top: 0; }
    .tk-st-info { flex: 1; min-width: 0; }
    .tk-st-info a { color: inherit; }
    .tk-klein { color: var(--hh-text-color-soft, #777); font-size: 0.9em; }
    .tk-preis { font-size: 1.6em; font-weight: 700; white-space: nowrap; font-variant-numeric: tabular-nums; text-align: right; min-width: 80px; }
    .tk-preis sup { font-size: 0.55em; }
    .tk-preis.tk-min { color: #2e7d32; }
    .tk-andere { font-size: 0.8em; color: var(--hh-text-color-soft, #777); white-space: nowrap; text-align: right; font-weight: normal; }
    .tk-zu, .tk-zu .tk-preis { color: var(--hh-text-color-soft, #5f6368); } /* statt Deckkraft: Kontrast bleibt ≥ 4,5:1 */
    .tk-marke { display: inline-block; font-size: 0.75em; padding: 1px 6px; border-radius: 8px; margin-left: 4px; vertical-align: 1px; }
    .tk-marke-offen { background: #e6f4e7; color: #1b5e20; }
    .tk-marke-zu { background: #f1f1f1; color: #555; }
    .tk-stunden { display: flex; align-items: center; gap: 2px; height: 120px; position: relative; margin: 8px 0 30px; }
    .tk-stunden::after { content: ""; position: absolute; left: 0; right: 0; top: 50%; border-top: 1px solid #c4c9cf; }
    .tk-h { flex: 1; height: 100%; position: relative; }
    .tk-h span { position: absolute; left: 0; right: 0; border-radius: 3px; }
    .tk-h .tk-guenstig { background: #2e7d32; top: 50%; border-radius: 0 0 3px 3px; }
    .tk-h .tk-teuer { background: #b8750c; bottom: 50%; border-radius: 3px 3px 0 0; }
    .tk-h i { position: absolute; top: 100%; left: 0; right: 0; text-align: center; font-size: 10px; font-style: normal; color: var(--hh-text-color-soft, #5f6368); margin-top: 4px; }
    @media (max-width: 600px) { .tk-h:nth-child(odd) i { visibility: hidden; } }
</style>
<div class="<?= $this->context->contentContainer ? '' : 'container' ?>">
    <div class="panel panel-default">
        <div class="panel-heading"><?= $t('<strong>Spritpreise</strong> rund um Uffenheim') ?></div>
        <div class="panel-body">
            <?php if ($daten === null): ?>
                <div class="alert alert-warning"><?= $t('Die Preise sind gerade nicht abrufbar. Bitte versuch es in ein paar Minuten noch einmal.') ?></div>
            <?php else: ?>
                <div class="tk-filter">
                    <div class="btn-group">
                        <?php foreach (Spritpreise::SORTEN as $k => $n): ?><?= $link($n, ['sorte' => $k], $k === $sorte) ?><?php endforeach ?>
                    </div>
                    <div class="btn-group">
                        <?php foreach ($module->umkreise as $km): ?><?= $link($t('{n} km', ['n' => $km]), ['umkreis' => $km], $km === $umkreis) ?><?php endforeach ?>
                    </div>
                    <div class="btn-group">
                        <?= $link($t('nach Preis'), ['nach' => 'preis'], $nachPreis) ?><?= $link($t('nach Entfernung'), ['nach' => 'km'], !$nachPreis) ?>
                    </div>
                </div>
                <?php if (!$liste): ?>
                    <p><?= $t('Keine Tankstelle in diesem Umkreis.') ?></p>
                <?php endif ?>
                <?php foreach ($liste as $s): ?>
                    <div class="tk-st<?= $s['offen'] ? '' : ' tk-zu' ?>">
                        <div class="tk-st-info">
                            <strong><?= Html::encode(Spritpreise::name($s)) ?></strong>
                            <span class="tk-marke <?= $s['offen'] ? 'tk-marke-offen' : 'tk-marke-zu' ?>"><?= $s['offen'] ? $t('geöffnet') : $t('geschlossen') ?></span><br>
                            <?= Html::a(Html::encode($s['strasse'] . ', ' . $s['plz'] . ' ' . $s['ort']), Spritpreise::kartenUrl($s), ['target' => '_blank', 'rel' => 'noopener']) ?>
                            <span class="tk-klein">· <?= Html::encode(str_replace('.', ',', (string)$s['km'])) ?> km</span>
                        </div>
                        <div>
                            <div class="tk-preis<?= $s['offen'] && $s[$sorte] && $s[$sorte] == $tiefst ? ' tk-min' : '' ?>"><?= Spritpreise::preis($s[$sorte]) ?></div>
                            <div class="tk-andere"><?= implode(' · ', array_map(fn($k) => Html::encode(Spritpreise::SORTEN[$k]) . ' ' . Spritpreise::preis($s[$k]),
                                    array_diff(array_keys(Spritpreise::SORTEN), [$sorte]))) ?></div>
                        </div>
                    </div>
                <?php endforeach ?>
                <p class="tk-klein" style="margin-top:12px">
                    <?= $t('Stand: {zeit}', ['zeit' => Spritpreise::zeit($daten['abgerufen'])]) ?>.
                    <?= $t('Preise in Euro je Liter, Entfernung Luftlinie ab Marktplatz. Angaben ohne Gewähr – maßgeblich ist der Preis an der Zapfsäule.') ?>
                </p>
            <?php endif ?>
        </div>
    </div>

    <div class="panel panel-default">
        <div class="panel-heading"><?= $t('<strong>Wann ist Tanken günstig?</strong> {sorte} in Uffenheim', ['sorte' => Html::encode(Spritpreise::SORTEN[$sorte])]) ?></div>
        <div class="panel-body">
            <?php if ($verlauf === null): ?>
                <p class="tk-klein"><?= $t('Wir sammeln dafür gerade noch Preise. Nach ein paar Tagen steht hier, zu welcher Uhrzeit es meist am günstigsten ist.') ?></p>
            <?php else:
                $werte = array_filter($verlauf['stunden'], fn($v) => $v !== null);
                $max = max(1, max(array_map('abs', $werte)));
                $best = array_keys($werte, min($werte))[0];
                $teuerst = array_keys($werte, max($werte))[0];
                ?>
                <p><?= $t('Meist am günstigsten gegen {gut} Uhr, am teuersten gegen {teuer} Uhr – im Schnitt {cent} Cent Unterschied je Liter.', [
                        'gut' => $best, 'teuer' => $teuerst, 'cent' => str_replace('.', ',', (string)round(max($werte) - min($werte), 1))]) ?></p>
                <div class="tk-stunden" role="img" aria-label="<?= $t('Abweichung vom Tagesmittel je Stunde') ?>">
                    <?php foreach ($verlauf['stunden'] as $h => $v): ?>
                        <div class="tk-h" title="<?= $v === null ? sprintf('%02d', $h) . ' Uhr: –' : Html::encode(sprintf('%02d', $h) . ' Uhr: ' . ($v > 0 ? '+' : '') . str_replace('.', ',', (string)$v) . ' ct') ?>">
                            <?php if ($v !== null && $v < 0): ?><span class="tk-guenstig" style="height:<?= round(-$v / $max * 50, 1) ?>%"></span><?php endif ?>
                            <?php if ($v !== null && $v > 0): ?><span class="tk-teuer" style="height:<?= round($v / $max * 50, 1) ?>%"></span><?php endif ?>
                            <i><?= $h ?></i>
                        </div>
                    <?php endforeach ?>
                </div>
                <p class="tk-klein"><?= $t('Grün nach unten: günstiger als im Tagesschnitt, Ocker nach oben: teurer. Grundlage: die niedrigsten Preise geöffneter Tankstellen im Umkreis von {km} km, beobachtet an {tage} Tagen.', [
                        'km' => $module->kastenRadius, 'tage' => $verlauf['tage']]) ?></p>
            <?php endif ?>
        </div>
    </div>

    <p class="tk-klein" style="font-size:0.8em">
        <?= $t('Daten: Markttransparenzstelle für Kraftstoffe (MTS-K) des Bundeskartellamts, bereitgestellt von {tk} unter der Lizenz {cc}.', [
            'tk' => Html::a('Tankerkönig', Spritpreise::QUELLE, ['target' => '_blank', 'rel' => 'noopener']),
            'cc' => Html::a('CC BY 4.0', 'https://creativecommons.org/licenses/by/4.0/deed.de', ['target' => '_blank', 'rel' => 'noopener'])]) ?>
        <?= $t('Falsche Preise kannst du der {mtsk} melden.', ['mtsk' => Html::a('MTS-K', Spritpreise::MTSK, ['target' => '_blank', 'rel' => 'noopener'])]) ?>
    </p>
</div>
