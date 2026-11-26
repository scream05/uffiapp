<?php
/**
 * Anmeldung durch Kursverwalter bearbeiten (keine Mail an die Eltern)
 *
 * @var \humhub\modules\kurse\models\Anmeldung $a
 * @var \humhub\modules\kurse\models\Kurs $kurs
 */

use humhub\helpers\Html;
use humhub\modules\kurse\models\Kurs;
use humhub\widgets\form\ActiveForm;

$zahlarten = $kurs->hatPreis() ? $kurs->zahlarten() : [];
$zurueck = $kurs->createUrl('/kurse/kurs/teilnehmer', ['id' => $kurs->id, 'liste' => $a->status]) . '#a' . $a->id;
?>
<div class="panel panel-default">
    <div class="panel-heading"><strong><?= Yii::t('KurseModule.base', 'Anmeldung bearbeiten: {kind}', ['kind' => Html::encode($a->getKindName())]) ?></strong>
        <span class="text-muted small"> · <?= Html::encode($kurs->titel) ?> · <?= $a->getNummer() ?> · <?= Html::encode($a->getStatusText()) ?></span></div>
    <div class="panel-body">
        <p class="help-block"><?= Yii::t('KurseModule.base', 'Hier änderst du die Angaben dieser Anmeldung. Die Eltern bekommen dabei keine Mail, Platz bzw. Wartelisten-Position bleiben unverändert. Mails an eine geänderte Adresse gehen erst bei der nächsten Nachricht dorthin.') ?></p>
        <?php $f = ActiveForm::begin(['id' => 'kurs-bearbeiten']) ?>
        <div class="row">
            <div class="col-md-4"><?= $f->field($a, 'kind_vorname') ?></div>
            <div class="col-md-4"><?= $f->field($a, 'kind_nachname') ?></div>
            <div class="col-md-4"><?= $f->field($a, 'kind_geburtsdatum')->input('date') ?></div>
        </div>
        <?= $f->field($a, 'schwimmstand')->textarea(['rows' => 2]) ?>
        <?= $f->field($a, 'mitglied')->checkbox() ?>
        <?= $f->field($a, 'eltern_name') ?>
        <div class="row">
            <div class="col-md-4"><?= $f->field($a, 'email')->input('email') ?></div>
            <div class="col-md-4"><?= $f->field($a, 'telefon')->input('tel') ?></div>
            <div class="col-md-4"><?= $f->field($a, 'notfall_telefon')->input('tel') ?></div>
        </div>
        <?php if ($zahlarten): ?>
            <?= $f->field($a, 'zahlart')->radioList($zahlarten) ?>
            <div class="row">
                <div class="col-md-4"><?= $f->field($a, 'betrag')->input('number', ['step' => '0.01', 'min' => 0])
                        ->hint(Yii::t('KurseModule.base', 'Laut Kurs: Mitglieder {mitglied}, Nichtmitglieder {nicht}', [
                            'mitglied' => Kurs::euro($kurs->preisFuer(true)), 'nicht' => Kurs::euro($kurs->preisFuer(false))])) ?></div>
                <div class="col-md-4"><?= $f->field($a, 'bezahlt_am')->input('date') ?></div>
            </div>
        <?php endif ?>
        <?= $f->field($a, 'notiz')->textInput(['maxlength' => 1000]) ?>
        <button type="submit" class="btn btn-primary"><?= Yii::t('KurseModule.base', 'Speichern (ohne Mail)') ?></button>
        <?= Html::a(Html::encode(Yii::t('KurseModule.base', 'Abbrechen')), $zurueck, ['class' => 'btn btn-link']) ?>
        <?php ActiveForm::end() ?>
    </div>
</div>
