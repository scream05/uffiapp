<?php
/** @var \humhub\modules\kurse\models\Anmeldung $a */

use humhub\helpers\Html;
?>
<div class="breite schmal abschnitt">
    <div class="karte">
        <h1 style="margin-top:0;font-size:1.6em"><?= Yii::t('KurseModule.base', 'Anmeldung bestätigen') ?></h1>
        <p><?= Yii::t('KurseModule.base', '{kind} für {kurs}', ['kind' => '<strong>' . Html::encode($a->getKindName()) . '</strong>', 'kurs' => '<strong>' . Html::encode($a->kurs->titel) . '</strong>']) ?><br>
            <span class="grau"><?= Html::encode($a->kurs->getTermineText()) ?> · <?= Html::encode($a->kurs->ort) ?></span></p>
        <?= Html::beginForm() ?>
        <button type="submit" class="knopf"><?= Yii::t('KurseModule.base', 'Anmeldung jetzt bestätigen') ?></button>
        <?= Html::endForm() ?>
    </div>
</div>
