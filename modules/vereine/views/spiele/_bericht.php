<?php
/**
 * Spielbericht aus dem Liveticker des BFV (Tore, Karten, Anpfiff/Halbzeit/Abpfiff, Kommentare des Vereins)
 * @var \humhub\modules\vereine\models\VereinSpiel $spiel
 * @var bool $offen aufgeklappt zeigen
 */

use humhub\helpers\Html;

$eintraege = $spiel->getTickerList();
if (!$eintraege) {
    return;
}
$offen ??= false;
$symbol = [
    'tor' => '<i class="fa fa-futbol-o" aria-hidden="true"></i>',
    'gelb' => '<span class="fvs-karte fvs-karte-gelb" aria-hidden="true"></span>',
    'rot' => '<span class="fvs-karte fvs-karte-rot" aria-hidden="true"></span>',
    'gelbrot' => '<span class="fvs-karte fvs-karte-gelbrot" aria-hidden="true"></span>',
    'phase' => '<i class="fa fa-flag-o" aria-hidden="true"></i>',
    'info' => '<i class="fa fa-comment-o" aria-hidden="true"></i>',
];
$tore = count(array_filter($eintraege, fn($e) => $e['art'] === 'tor'));
$karten = count(array_filter($eintraege, fn($e) => in_array($e['art'], ['gelb', 'rot', 'gelbrot'], true)));
?>
<details class="fvs-bericht"<?= $offen ? ' open' : '' ?>>
    <summary><i class="fa fa-list-ul"></i> <?= $spiel->laeuft() ? Yii::t('VereineModule.base', 'Liveticker') : Yii::t('VereineModule.base', 'Spielbericht') ?>
        <span class="text-muted">(<?= Yii::t('VereineModule.base', '{tore, plural, =0{keine Tore} =1{1 Tor} other{# Tore}}, {karten, plural, =0{keine Karten} =1{1 Karte} other{# Karten}}', ['tore' => $tore, 'karten' => $karten]) ?>)</span></summary>
    <ol>
        <?php foreach ($eintraege as $e): ?>
            <li class="fvs-e-<?= Html::encode($e['art']) ?>">
                <span class="fvs-min"><?= $e['m'] !== null ? (int)$e['m'] . '.' : '' ?></span>
                <span class="fvs-sym"><?= $symbol[$e['art']] ?? '' ?></span>
                <span class="fvs-txt">
                    <?= Html::encode($e['t']) ?><?php if ($e['art'] === 'tor' && $e['s']): ?> <strong><?= Html::encode($e['s']) ?></strong><?php endif ?>
                    <?php if ($e['k'] !== ''): ?><span class="fvs-kommentar"><?= Html::encode($e['k']) ?></span><?php endif ?>
                </span>
            </li>
        <?php endforeach ?>
    </ol>
    <small class="text-muted"><?= Yii::t('VereineModule.base', 'Aus dem Liveticker beim BFV.') ?> <?= Html::a(Yii::t('VereineModule.base', 'Zum Spiel'), $spiel->getUrl() . '/liveticker', ['target' => '_blank', 'rel' => 'noopener']) ?></small>
</details>
