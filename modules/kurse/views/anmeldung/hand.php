<?php
/**
 * @var \humhub\modules\kurse\models\Anmeldung $a
 * @var \humhub\modules\kurse\models\Kurs $kurs
 */

use humhub\helpers\Html;
use humhub\widgets\form\ActiveForm;

$zahlarten = $kurs->hatPreis() ? $kurs->zahlarten() : [];
?>
<div class="panel panel-default">
    <div class="panel-heading"><strong>Von Hand eintragen: <?= Html::encode($kurs->titel) ?></strong></div>
    <div class="panel-body">
        <p class="help-block">Für Anmeldungen auf Papier oder am Telefon. Ist ein Platz frei (und niemand auf der Warteliste), bekommt das Kind sofort einen Platz, sonst kommt es ans Ende der Warteliste.
            Bitte nur eintragen, wenn die Eltern den Datenschutzhinweisen zugestimmt haben.</p>
        <?php $f = ActiveForm::begin(['id' => 'kurs-hand']) ?>
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
        <?php endif ?>
        <?= $f->field($a, 'notiz')->textInput(['maxlength' => 1000]) ?>
        <div class="form-check mb-3">
            <?= Html::checkbox('mail', true, ['class' => 'form-check-input', 'id' => 'kurs-hand-mail']) ?>
            <label class="form-check-label" for="kurs-hand-mail">Eltern per Mail informieren (Zusage bzw. Warteliste, mit Zahlungsinfo), falls E-Mail angegeben</label>
        </div>
        <button type="submit" class="btn btn-primary">Eintragen</button>
        <?= Html::a('Abbrechen', $kurs->createUrl('/kurse/kurs/teilnehmer', ['id' => $kurs->id]), ['class' => 'btn btn-link']) ?>
        <?php ActiveForm::end() ?>
    </div>
</div>
