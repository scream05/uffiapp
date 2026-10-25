<?php
/**
 * Ein Spiel als Zeile (Spielplan, Ergebnisse, Kasten auf der Vereinsseite)
 * @var \humhub\modules\vereine\models\VereinSpiel $spiel
 * @var string $label Mannschaft, z. B. „Herren II“
 * @var bool $mitDatum Datum in der Zeile zeigen (sonst steht es in der Überschrift darüber)
 */

use humhub\helpers\Html;
use humhub\modules\vereine\components\Bfv;

$mitDatum ??= false;
$wertung = $spiel->getWertung();
$zustand = $spiel->getZustand();
$t = $spiel->getZeitpunkt();
// Lange Namen von Spielgemeinschaften („(SG) A/B/C“) nach den Schrägstrichen umbrechen
$name = function (string $n, bool $eigen) {
    $html = str_replace('/', '/<wbr>', Html::encode($n));
    return $eigen ? Html::tag('strong', $html) : $html;
};
$wertungText = ['s' => Yii::t('VereineModule.base', 'gewonnen'), 'u' => Yii::t('VereineModule.base', 'unentschieden'), 'n' => Yii::t('VereineModule.base', 'verloren')];
?>
<a class="fvs-spiel" href="<?= Html::encode($spiel->getUrl()) ?>" target="_blank" rel="noopener"
   title="<?= Html::encode(Yii::t('VereineModule.base', 'Spiel beim BFV ansehen')) ?>">
    <span class="fvs-zeit">
        <?php if ($mitDatum): ?><span class="fvs-tag"><?= Html::encode(Bfv::format($t, 'EEEdM')) ?></span><?php endif ?>
        <?= $spiel->uhrzeit ? Html::encode(Bfv::format($t, 'Hm')) : '–' ?>
    </span>
    <span class="fvs-mitte">
        <span class="fvs-kopf">
            <span class="fvs-team"><?= Html::encode($label) ?></span>
            <span class="fvs-ort-art"><?= $spiel->heim_eigen ? '<i class="fa fa-home"></i> ' . Yii::t('VereineModule.base', 'Heimspiel') : Yii::t('VereineModule.base', 'Auswärts') ?></span>
            <?php if ($spiel->wettbewerb_typ && $spiel->wettbewerb_typ !== 'Meisterschaften'): ?>
                <span class="fvs-wettbewerb"><?= Html::encode(Yii::t('VereineModule.base', $spiel->wettbewerb_typ)) ?></span>
            <?php endif ?>
        </span>
        <span class="fvs-paarung"><?= $name($spiel->heim, (bool)$spiel->heim_eigen) ?> – <?= $name($spiel->gast, !$spiel->heim_eigen) ?></span>
        <?php if ($spiel->ort && !$spiel->istVorbei()): ?>
            <span class="fvs-ort"><i class="fa fa-map-marker"></i> <?= Html::encode(Bfv::ortKurz($spiel->ort)) ?></span>
        <?php endif ?>
    </span>
    <?php if ($wertung !== null): ?>
        <span class="fvs-ergebnis fvs-<?= $wertung ?>" title="<?= Html::encode($wertungText[$wertung]) ?>">
            <?= Html::encode($spiel->ergebnis) ?><span class="visually-hidden"> (<?= Html::encode($wertungText[$wertung]) ?>)</span>
        </span>
    <?php elseif ($zustand !== null): ?>
        <span class="fvs-ergebnis fvs-zustand"><?= Html::encode($zustand) ?></span>
    <?php elseif ($spiel->istVorbei()): ?>
        <span class="fvs-ergebnis fvs-zustand"><?= Yii::t('VereineModule.base', 'Ergebnis folgt') ?></span>
    <?php endif ?>
</a>
