<?php
/** @var \humhub\modules\kurse\models\Kurs $kurs */

use humhub\helpers\Html;
?>
<div class="breite schmal abschnitt">
    <p><a href="<?= Html::encode($kurs->elternUrl('kurs')) ?>">← Zum Kurs</a></p>
    <div class="karte">
        <h1 style="margin-top:0;font-size:1.6em">Datenschutzhinweise zur Kursanmeldung</h1>
        <?= $this->render('@kurse/views/kurs/_datenschutz_text', ['kurs' => $kurs, 'allgemeinUrl' => $this->context->seite->seiteUrl('datenschutzseite')]) ?>
    </div>
</div>
