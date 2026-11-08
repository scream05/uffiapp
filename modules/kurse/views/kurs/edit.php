<?php
/**
 * @var \humhub\modules\kurse\models\Kurs $kurs
 */

use humhub\helpers\Html;
use humhub\widgets\form\ActiveForm;

$space = $this->context->contentContainer;
$neu = $kurs->isNewRecord;
$dt = fn($v) => $v ? str_replace(' ', 'T', substr($v, 0, 16)) : '';
?>
<div class="panel panel-default">
    <div class="panel-heading"><strong><?= $neu ? 'Neuer Kurs' : 'Kurs bearbeiten' ?>: <?= Html::encode($kurs->verein->name) ?></strong></div>
    <div class="panel-body">
        <?php if ($neu): ?>
            <p class="help-block">Der Kurs wird zuerst als Entwurf gespeichert und ist erst sichtbar, wenn du ihn veröffentlichst.</p>
        <?php endif ?>
        <?php $f = ActiveForm::begin(['id' => 'kurs-form']) ?>

        <h4>Kurs</h4>
        <div class="row">
            <div class="col-md-7"><?= $f->field($kurs, 'titel')->textInput(['maxlength' => 150, 'placeholder' => 'z. B. Seepferdchenkurs Frühjahr']) ?></div>
            <div class="col-md-5"><?= $f->field($kurs, 'niveau')->textInput(['maxlength' => 100, 'placeholder' => 'z. B. Seepferdchen, Bronze']) ?></div>
        </div>
        <?= $f->field($kurs, 'beschreibung')->textarea(['rows' => 4, 'placeholder' => 'Was lernen die Kinder, was sollen sie mitbringen …']) ?>
        <div class="row">
            <div class="col-6 col-md-3"><?= $f->field($kurs, 'alter_min')->input('number', ['step' => '0.5', 'min' => 0, 'placeholder' => 'z. B. 5']) ?></div>
            <div class="col-6 col-md-3"><?= $f->field($kurs, 'alter_max')->input('number', ['step' => '0.5', 'min' => 0, 'placeholder' => 'leer = egal']) ?></div>
            <div class="col-6 col-md-3"><?= $f->field($kurs, 'plaetze')->input('number', ['min' => 1]) ?></div>
            <div class="col-6 col-md-3"><?= $f->field($kurs, 'warteliste_max')->input('number', ['min' => 0, 'placeholder' => 'leer = unbegrenzt']) ?></div>
        </div>
        <?= $f->field($kurs, 'nur_mitglieder')->checkbox() ?>

        <h4>Ort und Termine</h4>
        <?= $f->field($kurs, 'ort')->textInput(['maxlength' => 255, 'placeholder' => 'z. B. Hallenbad Uffenheim']) ?>
        <div class="row">
            <div class="col-6 col-md-3"><?= $f->field($kurs, 'beginn')->input('date') ?></div>
            <div class="col-6 col-md-3"><?= $f->field($kurs, 'ende')->input('date')->hint('leer = aus Einheiten berechnet (wöchentlich)') ?></div>
            <div class="col-4 col-md-2"><?= $f->field($kurs, 'zeit_von')->input('time', ['value' => $kurs->zeit_von ? substr($kurs->zeit_von, 0, 5) : '']) ?></div>
            <div class="col-4 col-md-2"><?= $f->field($kurs, 'zeit_bis')->input('time', ['value' => $kurs->zeit_bis ? substr($kurs->zeit_bis, 0, 5) : '']) ?></div>
            <div class="col-4 col-md-2"><?= $f->field($kurs, 'einheiten')->input('number', ['min' => 1]) ?></div>
        </div>
        <?= $f->field($kurs, 'termine_hinweis')->textInput(['maxlength' => 255, 'placeholder' => 'z. B. immer dienstags, nicht in den Ferien']) ?>

        <h4>Anmeldung</h4>
        <div class="row">
            <div class="col-md-6"><?= $f->field($kurs, 'anmeldung_ab')->input('datetime-local', ['value' => $dt($kurs->anmeldung_ab)])->hint('leer = sofort nach dem Veröffentlichen') ?></div>
            <div class="col-md-6"><?= $f->field($kurs, 'anmeldung_bis')->input('datetime-local', ['value' => $dt($kurs->anmeldung_bis)])->hint('leer = bis zum ersten Kurstag') ?></div>
        </div>
        <div class="row">
            <div class="col-md-8"><?= $f->field($kurs, 'kontakt_email')->input('email')->hint('Antworten der Eltern auf die Mails landen hier.') ?></div>
            <div class="col-md-4"><?= $f->field($kurs, 'erinnerung_tage')->input('number', ['min' => 1, 'max' => 30])->hint('leer = keine Erinnerung') ?></div>
        </div>
        <?= $f->field($kurs, 'bedingungen')->textarea(['rows' => 5, 'placeholder' => 'z. B. Abmeldung, Rückerstattung, Aufsicht, Verhalten im Bad …'])->hint('Eltern müssen sie bei der Anmeldung bestätigen.') ?>

        <h4>Bezahlung</h4>
        <div class="row">
            <div class="col-6 col-md-3"><?= $f->field($kurs, 'preis_mitglied')->input('number', ['step' => '0.01', 'min' => 0]) ?></div>
            <div class="col-6 col-md-3"><?= $f->field($kurs, 'preis_nichtmitglied')->input('number', ['step' => '0.01', 'min' => 0]) ?></div>
            <div class="col-md-6 pt-md-4">
                <?= $f->field($kurs, 'zahlart_ueberweisung')->checkbox() ?>
                <?= $f->field($kurs, 'zahlart_bar')->checkbox() ?>
            </div>
        </div>
        <div class="row">
            <div class="col-md-4"><?= $f->field($kurs, 'kontoinhaber')->textInput(['maxlength' => 100]) ?></div>
            <div class="col-md-5"><?= $f->field($kurs, 'iban')->textInput(['maxlength' => 40, 'value' => $kurs->iban ? trim(chunk_split($kurs->iban, 4, ' ')) : '']) ?></div>
            <div class="col-md-3"><?= $f->field($kurs, 'zahlungsfrist_tage')->input('number', ['min' => 1, 'max' => 90])->hint('leer = keine Erinnerung') ?></div>
        </div>
        <p class="help-block">Verwendungszweck wird automatisch erzeugt: Anmeldenummer + Name des Kindes (z. B. „K3-17 Max Muster“).</p>

        <button type="submit" class="btn btn-primary"><?= $neu ? 'Als Entwurf speichern' : 'Speichern' ?></button>
        <?= Html::a('Abbrechen', $neu ? $space->createUrl('/kurse/kurs/index') : $kurs->getUrl(), ['class' => 'btn btn-link']) ?>
        <?php ActiveForm::end() ?>
    </div>
</div>
