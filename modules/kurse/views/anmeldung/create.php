<?php
/**
 * @var \humhub\modules\kurse\models\Anmeldung $a
 * @var \humhub\modules\kurse\models\Kurs $kurs
 */

use humhub\helpers\Html;
use humhub\widgets\form\ActiveForm;

$space = $this->context->contentContainer;
$zahlarten = $kurs->hatPreis() ? $kurs->zahlarten() : [];
?>
<div class="panel panel-default">
    <div class="panel-heading"><strong>Anmeldung: <?= Html::encode($kurs->titel) ?></strong></div>
    <div class="panel-body">
        <p><?= Html::a('<i class="fa fa-arrow-left"></i> Zum Kurs', $kurs->getUrl()) ?></p>
        <p class="text-muted"><?= Html::encode($kurs->verein->name) ?> · <?= Html::encode($kurs->getTermineText()) ?> · <?= Html::encode($kurs->ort) ?></p>
        <?php if ($kurs->freiePlaetze() === 0): ?>
            <div class="alert alert-warning">Der Kurs ist gerade voll. Nach der Bestätigung kommt dein Kind auf die Warteliste und rückt automatisch nach, wenn ein Platz frei wird.</div>
        <?php endif ?>
        <?php $f = ActiveForm::begin(['id' => 'kurs-anmeldung']) ?>
        <h4>Kind</h4>
        <div class="row">
            <div class="col-md-4"><?= $f->field($a, 'kind_vorname')->textInput(['maxlength' => 100, 'autocomplete' => 'off']) ?></div>
            <div class="col-md-4"><?= $f->field($a, 'kind_nachname')->textInput(['maxlength' => 100, 'autocomplete' => 'off']) ?></div>
            <div class="col-md-4"><?= $f->field($a, 'kind_geburtsdatum')->input('date', ['max' => date('Y-m-d')]) ?></div>
        </div>
        <?= $f->field($a, 'schwimmstand')->textarea(['rows' => 2, 'maxlength' => 1000, 'placeholder' => 'z. B. wassergewöhnt, taucht schon, schwimmt mit Schwimmflügeln …'])->hint('freiwillig – hilft der Kursleitung bei der Einteilung') ?>
        <?= $f->field($a, 'mitglied')->checkbox() ?>

        <h4>Erziehungsberechtigte/r</h4>
        <?= $f->field($a, 'eltern_name')->textInput(['maxlength' => 150, 'autocomplete' => 'name']) ?>
        <div class="row">
            <div class="col-md-6"><?= $f->field($a, 'email')->input('email', ['autocomplete' => 'email'])->hint('Hierhin schicken wir den Bestätigungslink.') ?></div>
            <div class="col-md-6"><?= $f->field($a, 'telefon')->input('tel', ['autocomplete' => 'tel']) ?></div>
        </div>
        <?= $f->field($a, 'notfall_telefon')->input('tel')->hint('freiwillig, falls abweichend – z. B. wenn dein Kind von jemand anderem gebracht wird') ?>

        <?php if ($zahlarten): ?>
            <h4>Bezahlung</h4>
            <p>Kursgebühr: <?= Html::encode($kurs->getPreisText()) ?></p>
            <?= $f->field($a, 'zahlart')->radioList($zahlarten) ?>
        <?php endif ?>

        <div style="position:absolute;left:-5000px" aria-hidden="true"><?= Html::activeTextInput($a, 'webseite', ['tabindex' => -1, 'autocomplete' => 'off']) ?></div>

        <?php if ($kurs->bedingungen): ?>
            <details class="mb-2"><summary>Teilnahmebedingungen lesen</summary><p class="mt-2"><?= nl2br(Html::encode($kurs->bedingungen), false) ?></p></details>
            <?= $f->field($a, 'bedingungen_ok')->checkbox(['label' => 'Ich habe die Teilnahmebedingungen gelesen und bin einverstanden.']) ?>
        <?php else: ?>
            <?php $a->bedingungen_ok = 1 ?><?= Html::activeHiddenInput($a, 'bedingungen_ok') ?>
        <?php endif ?>
        <?= $f->field($a, 'datenschutz_ok')->checkbox(['label' => 'Ich habe die ' . Html::a('Datenschutzhinweise', $space->createUrl('/kurse/kurs/datenschutz', ['id' => $kurs->id]), ['target' => '_blank']) . ' gelesen und bin erziehungsberechtigt.']) ?>

        <button type="submit" class="btn btn-primary">Anmeldung absenden</button>
        <p class="help-block mt-2">Du bekommst gleich eine Mail mit einem Link. Erst mit dem Klick auf diesen Link ist die Anmeldung gültig.</p>
        <?php ActiveForm::end() ?>
    </div>
</div>
