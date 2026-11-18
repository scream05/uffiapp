<?php
/** @var \humhub\modules\kurse\models\Kurs $kurs @var string $betreff @var string $nachricht @var string $an @var int[] $anzahl */

use humhub\helpers\Html;
?>
<div class="panel panel-default">
    <div class="panel-heading"><strong><?= Yii::t('KurseModule.base', 'Rundmail: {kurs}', ['kurs' => Html::encode($kurs->titel)]) ?></strong></div>
    <div class="panel-body">
        <p class="help-block"><?= Yii::t('KurseModule.base', 'Die Mail beginnt mit einer persönlichen Anrede und endet mit dem persönlichen Link zur Anmeldung, beides in der Sprache, in der sich die Eltern angemeldet haben. Antworten gehen an {email}.', ['email' => Html::encode($kurs->kontakt_email)]) ?></p>
        <?= Html::beginForm() ?>
        <div class="mb-3">
            <label class="form-label"><?= Yii::t('KurseModule.base', 'An') ?></label>
            <?= Html::radioList('an', $an, [
                'platz' => Yii::t('KurseModule.base', 'alle Teilnehmer ({n})', ['n' => $anzahl['platz']]),
                'warteliste' => Yii::t('KurseModule.base', 'nur Warteliste ({n})', ['n' => $anzahl['warteliste']]),
                'alle' => Yii::t('KurseModule.base', 'Teilnehmer und Warteliste ({n})', ['n' => $anzahl['alle']]),
            ], ['class' => 'd-flex flex-wrap gap-3']) ?>
        </div>
        <div class="mb-3">
            <label class="form-label" for="betreff"><?= Yii::t('KurseModule.base', 'Betreff') ?></label>
            <?= Html::textInput('betreff', $betreff, ['class' => 'form-control', 'id' => 'betreff', 'maxlength' => 150]) ?>
        </div>
        <div class="mb-3">
            <label class="form-label" for="nachricht"><?= Yii::t('KurseModule.base', 'Nachricht') ?></label>
            <?= Html::textarea('nachricht', $nachricht, ['class' => 'form-control', 'rows' => 8, 'id' => 'nachricht']) ?>
        </div>
        <?= Html::submitButton(Html::encode(Yii::t('KurseModule.base', 'Senden')), ['class' => 'btn btn-primary', 'data-confirm' => Yii::t('KurseModule.base', 'Rundmail jetzt verschicken?')]) ?>
        <?= Html::a(Html::encode(Yii::t('KurseModule.base', 'Abbrechen')), $kurs->createUrl('/kurse/kurs/teilnehmer', ['id' => $kurs->id]), ['class' => 'btn btn-link']) ?>
        <?= Html::endForm() ?>
    </div>
</div>
