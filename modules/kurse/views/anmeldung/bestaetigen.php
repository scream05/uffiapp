<?php
/** @var \humhub\modules\kurse\models\Anmeldung $a */

use humhub\helpers\Html;
?>
<div class="panel panel-default">
    <div class="panel-body">
        <h1 style="font-size:1.4em"><?= Yii::t('KurseModule.base', 'Anmeldung bestätigen') ?></h1>
        <p><?= Yii::t('KurseModule.base', '{kind} für {kurs}', ['kind' => '<strong>' . Html::encode($a->getKindName()) . '</strong>', 'kurs' => '<strong>' . Html::encode($a->kurs->titel) . '</strong>']) ?>
            (<?= Html::encode($a->kurs->verein->name) ?>, <?= Html::encode($a->kurs->getTermineText()) ?>)</p>
        <?= Html::beginForm() ?>
        <?= Html::submitButton(Html::encode(Yii::t('KurseModule.base', 'Anmeldung jetzt bestätigen')), ['class' => 'btn btn-primary btn-lg']) ?>
        <?= Html::endForm() ?>
    </div>
</div>
