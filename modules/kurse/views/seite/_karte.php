<?php
/**
 * Kurs als Karte auf der eigenen Seite
 * @var \humhub\modules\kurse\models\Kurs $kurs
 */

use humhub\helpers\Html;
use humhub\modules\kurse\models\Kurs;

[$label, $farbe] = $kurs->getStatusLabel();
$entwurf = $kurs->status === Kurs::STATUS_ENTWURF;
?>
<article class="karte kurs-karte">
    <div class="kurs-karte-kopf">
        <h3><a href="<?= Html::encode($kurs->elternUrl('kurs')) ?>"><?= Html::encode($kurs->titel) ?></a></h3>
        <span class="marke-status s-<?= $entwurf ? 'default' : $farbe ?>"><?= Html::encode($entwurf ? 'Entwurf – nur für Kursverwalter' : $label) ?></span>
    </div>
    <?php if ($kurs->niveau): ?><p class="grau klein kurs-niveau"><?= Html::encode($kurs->niveau) ?></p><?php endif ?>
    <ul class="kurs-eckdaten">
        <li><span aria-hidden="true">📅</span> <?= Html::encode($kurs->getTermineText()) ?></li>
        <li><span aria-hidden="true">📍</span> <?= Html::encode($kurs->ort) ?></li>
        <?php if ($alter = $kurs->getAlterText()): ?><li><span aria-hidden="true">👧</span> <?= Html::encode($alter) ?></li><?php endif ?>
        <li><span aria-hidden="true">💶</span> <?= Html::encode($kurs->getPreisText()) ?></li>
    </ul>
    <a class="knopf" href="<?= Html::encode($kurs->elternUrl('kurs')) ?>">Details &amp; Anmeldung <span aria-hidden="true">→</span></a>
</article>
