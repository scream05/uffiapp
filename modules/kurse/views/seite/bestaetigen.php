<?php
/** @var \humhub\modules\kurse\models\Anmeldung $a */

use humhub\helpers\Html;
?>
<div class="breite schmal abschnitt">
    <div class="karte">
        <h1 style="margin-top:0;font-size:1.6em">Anmeldung bestätigen</h1>
        <p><strong><?= Html::encode($a->getKindName()) ?></strong> für <strong><?= Html::encode($a->kurs->titel) ?></strong><br>
            <span class="grau"><?= Html::encode($a->kurs->getTermineText()) ?> · <?= Html::encode($a->kurs->ort) ?></span></p>
        <?= Html::beginForm() ?>
        <button type="submit" class="knopf">Anmeldung jetzt bestätigen</button>
        <?= Html::endForm() ?>
    </div>
</div>
