<?php
/** @var \humhub\modules\kurse\models\Kurs $kurs @var int $betroffen */

use humhub\helpers\Html;
?>
<div class="panel panel-default">
    <div class="panel-heading"><strong><?= Yii::t('KurseModule.base', 'Kurs absagen: {kurs}', ['kurs' => Html::encode($kurs->titel)]) ?></strong></div>
    <div class="panel-body">
        <p><?= Yii::t('KurseModule.base', 'Alle {n} angemeldeten Kinder (mit Platz oder auf der Warteliste) werden abgemeldet, die Eltern bekommen eine Mail. Das lässt sich nicht rückgängig machen.', ['n' => (int)$betroffen]) ?></p>
        <?= Html::beginForm() ?>
        <div class="mb-3">
            <label class="form-label" for="grund"><?= Yii::t('KurseModule.base', 'Begründung für die Eltern (optional)') ?></label>
            <?= Html::textarea('grund', '', ['class' => 'form-control', 'rows' => 3, 'id' => 'grund', 'placeholder' => Yii::t('KurseModule.base', 'z. B. Das Hallenbad bleibt wegen Reparaturen geschlossen.')]) ?>
        </div>
        <?= Html::submitButton(Html::encode(Yii::t('KurseModule.base', 'Kurs absagen und Eltern informieren')), ['class' => 'btn btn-danger', 'data-confirm' => Yii::t('KurseModule.base', 'Kurs wirklich absagen?')]) ?>
        <?= Html::a(Html::encode(Yii::t('KurseModule.base', 'Abbrechen')), $kurs->getUrl(), ['class' => 'btn btn-link']) ?>
        <?= Html::endForm() ?>
    </div>
</div>
