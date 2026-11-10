<?php
/**
 * Eckdaten eines Kurses als Liste
 * @var \humhub\modules\kurse\models\Kurs $kurs
 */

use humhub\helpers\Html;
?>
<dl class="fakten">
    <dt>Termine</dt><dd><?= Html::encode($kurs->getTermineText()) ?><?= $kurs->termine_hinweis ? '<br><span class="grau">' . Html::encode($kurs->termine_hinweis) . '</span>' : '' ?></dd>
    <dt>Ort</dt><dd><?= Html::encode($kurs->ort) ?></dd>
    <?php if ($alter = $kurs->getAlterText()): ?><dt>Alter</dt><dd><?= Html::encode($alter) ?> (am ersten Kurstag)</dd><?php endif ?>
    <dt>Kosten</dt><dd><?= Html::encode($kurs->getPreisText()) ?><?php if ($kurs->hatPreis()): ?><br><span class="grau">Zahlung: <?= Html::encode(implode(' oder ', $kurs->zahlarten())) ?></span><?php endif ?></dd>
    <dt>Plätze</dt><dd><?= $kurs->plaetze ?><?= $kurs->nur_mitglieder ? ' · nur für Vereinsmitglieder' : '' ?></dd>
    <?php if ($kurs->anmeldung_ab || $kurs->anmeldung_bis): ?>
        <dt>Anmeldung</dt><dd><?= $kurs->anmeldung_ab ? 'ab ' . date('d.m.Y, H:i', strtotime($kurs->anmeldung_ab)) . ' Uhr' : '' ?>
            <?= $kurs->anmeldung_bis ? ' bis ' . date('d.m.Y, H:i', strtotime($kurs->anmeldung_bis)) . ' Uhr' : '' ?></dd>
    <?php endif ?>
    <dt>Kontakt</dt><dd><?= Html::mailto(Html::encode($kurs->kontakt_email), $kurs->kontakt_email) ?></dd>
</dl>
