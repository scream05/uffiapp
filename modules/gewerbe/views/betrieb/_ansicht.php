<?php
/**
 * Umschalter Liste / Karte (Filter bleiben erhalten)
 * @var string $aktiv liste | karte
 * @var string $q
 * @var string $kategorie
 * @var string $ortsteil
 */

use humhub\helpers\Html;

$space = $this->context->contentContainer;
$p = array_filter(['q' => $q, 'kategorie' => $kategorie, 'ortsteil' => $ortsteil], fn($v) => $v !== '');
?>
<nav class="gw-ansicht" aria-label="<?= Html::encode(Yii::t('GewerbeModule.base', 'Ansicht')) ?>">
    <?= Html::a('<i class="fa fa-list" aria-hidden="true"></i> ' . Yii::t('GewerbeModule.base', 'Liste'), $space->createUrl('/gewerbe/betrieb/index', $p),
        ['class' => 'btn btn-sm ' . ($aktiv === 'liste' ? 'btn-primary' : 'btn-light'), 'aria-current' => $aktiv === 'liste' ? 'page' : null]) ?>
    <?= Html::a('<i class="fa fa-map-o" aria-hidden="true"></i> ' . Yii::t('GewerbeModule.base', 'Karte'), $space->createUrl('/gewerbe/betrieb/karte', $p),
        ['class' => 'btn btn-sm ' . ($aktiv === 'karte' ? 'btn-primary' : 'btn-light'), 'aria-current' => $aktiv === 'karte' ? 'page' : null]) ?>
</nav>
<style <?= Html::nonce() ?>>
    .gw-ansicht { display: flex; gap: 6px; margin-bottom: 12px; }
</style>
