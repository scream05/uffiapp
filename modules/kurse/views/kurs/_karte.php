<?php
/**
 * Kurs als Karte in Listen (Übersicht, Vereinsseite)
 * @var \humhub\modules\kurse\models\Kurs $kurs
 * @var bool $mitVerein
 */

use humhub\helpers\Html;

[$label, $farbe] = $kurs->getStatusLabel();
?>
<a href="<?= Html::encode($kurs->getUrl()) ?>" class="kurs-karte">
    <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap">
        <strong class="kurs-karte-titel"><?= Html::encode($kurs->titel) ?></strong>
        <span class="badge text-bg-<?= $farbe ?>"><?= Html::encode($label) ?></span>
    </div>
    <?php if (!empty($mitVerein)): ?>
        <div class="text-muted small"><?= Html::encode($kurs->verein->name) ?></div>
    <?php endif ?>
    <div class="small mt-1">
        <i class="fa fa-calendar"></i> <?= Html::encode($kurs->getTermineText()) ?><br>
        <i class="fa fa-map-marker"></i> <?= Html::encode($kurs->ort) ?>
        <?php if ($alter = $kurs->getAlterText()): ?> · <i class="fa fa-child"></i> <?= Html::encode($alter) ?><?php endif ?>
        · <i class="fa fa-euro"></i> <?= Html::encode($kurs->getPreisText()) ?>
    </div>
</a>
