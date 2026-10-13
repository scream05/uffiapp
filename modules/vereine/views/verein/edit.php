<?php
/**
 * @var \humhub\modules\vereine\models\Verein $verein
 * @var bool $isNew
 * @var string $nachricht
 * @var \humhub\modules\vereine\Module $module
 */

use humhub\helpers\Html;
use humhub\modules\content\widgets\richtext\RichTextField;
use humhub\modules\file\widgets\FilePreview;
use humhub\modules\file\widgets\UploadButton;
use humhub\modules\file\widgets\UploadProgress;
use humhub\modules\vereine\models\Verein;
use humhub\modules\vereine\Module;
use humhub\widgets\form\ActiveForm;

$space = $this->context->contentContainer;
$logo = $isNew ? null : $verein->getLogo();
?>
<div class="panel panel-default">
    <div class="panel-heading"><strong><?= $isNew ? 'Verein vorschlagen' : 'Vereinsseite bearbeiten' ?></strong></div>
    <div class="panel-body">
        <?php if ($isNew && !Module::canManageAll()): ?>
            <p class="help-block">Ihr Vorschlag wird kurz geprüft. Danach erscheint der Verein im Verzeichnis und Sie können Seite und Termine pflegen.</p>
        <?php endif ?>
        <p class="help-block">Bitte nur Angaben, die öffentlich sein dürfen: allgemeine Kontaktdaten des Vereins
            (z. B. info@…), keine privaten Adressen oder Telefonnummern ohne Einverständnis der Person.</p>

        <?php $form = ActiveForm::begin(['id' => 'verein-form']) ?>
        <?= $form->field($verein, 'name')->textInput(['maxlength' => 150, 'readonly' => !$isNew && !Module::canManageAll()]) ?>
        <div class="row">
            <div class="col-sm-6"><?= $form->field($verein, 'kategorie')->dropDownList(array_combine($module->categories, $module->categories), ['prompt' => 'Bitte wählen …']) ?></div>
            <div class="col-sm-6"><?= $form->field($verein, 'ortsteil')->dropDownList(array_combine($module->ortsteile, $module->ortsteile)) ?></div>
        </div>
        <?= $form->field($verein, 'beschreibung')->widget(RichTextField::class, [
            'placeholder' => 'Wer sind wir, was machen wir, wann trifft man uns, wie kann man mitmachen?',
            'exclude' => ['mention', 'oembed', 'upload'],
            'pluginOptions' => ['maxHeight' => '400px'],
        ]) ?>
        <?= $form->field($verein, 'treffpunkt')->textInput(['placeholder' => 'z. B. Sportheim, Am Sportplatz 1, 97215 Uffenheim']) ?>
        <div class="row">
            <div class="col-sm-4"><?= $form->field($verein, 'website')->textInput(['placeholder' => 'https://…']) ?></div>
            <div class="col-sm-4"><?= $form->field($verein, 'email')->textInput(['type' => 'email', 'placeholder' => 'info@…']) ?></div>
            <div class="col-sm-4"><?= $form->field($verein, 'telefon')->textInput(['type' => 'tel']) ?></div>
        </div>

        <?php
        $apRows = array_values($verein->apRows);
        for ($i = count($apRows); $i < min(Verein::MAX_ANSPRECHPARTNER, count($apRows) + 2); $i++) {
            $apRows[] = ['funktion' => '', 'name' => '', 'email' => '', 'telefon' => ''];
        }
        $socialRows = array_values($verein->socialRows);
        for ($i = count($socialRows); $i < min(Verein::MAX_SOCIAL, count($socialRows) + 1); $i++) {
            $socialRows[] = ['typ' => '', 'url' => ''];
        }
        ?>
        <div class="form-group">
            <label>Ansprechpartner</label>
            <p class="help-block" style="margin-top:0">Nur Personen, die damit einverstanden sind, auf der Vereinsseite genannt zu werden. Leere Zeilen werden ignoriert.</p>
            <?php foreach ($apRows as $i => $row): ?>
                <div class="row verein-ap-row" style="margin-bottom:6px">
                    <div class="col-sm-3"><?= Html::textInput("Verein[apRows][$i][funktion]", $row['funktion'] ?? '', ['class' => 'form-control', 'placeholder' => 'Funktion, z. B. Jugendleiter', 'aria-label' => 'Funktion']) ?></div>
                    <div class="col-sm-3"><?= Html::textInput("Verein[apRows][$i][name]", $row['name'] ?? '', ['class' => 'form-control', 'placeholder' => 'Name', 'aria-label' => 'Name']) ?></div>
                    <div class="col-sm-3"><?= Html::textInput("Verein[apRows][$i][email]", $row['email'] ?? '', ['class' => 'form-control', 'type' => 'email', 'placeholder' => 'E-Mail (optional)', 'aria-label' => 'E-Mail']) ?></div>
                    <div class="col-sm-3"><?= Html::textInput("Verein[apRows][$i][telefon]", $row['telefon'] ?? '', ['class' => 'form-control', 'type' => 'tel', 'placeholder' => 'Telefon (optional)', 'aria-label' => 'Telefon']) ?></div>
                </div>
            <?php endforeach ?>
            <?= Html::error($verein, 'apRows', ['class' => 'text-danger']) ?>
            <?php if (count($apRows) < Verein::MAX_ANSPRECHPARTNER): ?><p class="help-block">Mehr Zeilen erscheinen nach dem Speichern.</p><?php endif ?>
        </div>

        <div class="form-group">
            <label>Social Media / weitere Links</label>
            <?php foreach ($socialRows as $i => $row): ?>
                <div class="row" style="margin-bottom:6px">
                    <div class="col-sm-3"><?= Html::textInput("Verein[socialRows][$i][typ]", $row['typ'] ?? '', ['class' => 'form-control', 'placeholder' => 'z. B. Instagram', 'aria-label' => 'Art']) ?></div>
                    <div class="col-sm-9"><?= Html::textInput("Verein[socialRows][$i][url]", $row['url'] ?? '', ['class' => 'form-control', 'placeholder' => 'https://…', 'aria-label' => 'Adresse']) ?></div>
                </div>
            <?php endforeach ?>
            <?= Html::error($verein, 'socialRows', ['class' => 'text-danger']) ?>
        </div>

        <div class="form-group">
            <label>Logo</label>
            <?php if ($logo): ?>
                <div style="margin-bottom:8px;display:flex;align-items:center;gap:12px">
                    <?= Html::img($logo->getUrl(), ['alt' => 'Aktuelles Logo', 'style' => 'height:64px;border-radius:10px']) ?>
                    <label style="font-weight:normal"><?= Html::checkbox('logoEntfernen', false) ?> Logo entfernen</label>
                </div>
            <?php endif ?>
            <?= UploadButton::widget([
                'id' => 'verein-upload',
                'label' => $logo ? 'Anderes Logo hochladen' : 'Logo hochladen',
                'tooltip' => false,
                'cssButtonClass' => 'btn-light',
                'progress' => '#verein-progress',
                'preview' => '#verein-preview',
                'max' => 1,
            ]) ?>
            <?= UploadProgress::widget(['id' => 'verein-progress']) ?>
            <?= FilePreview::widget(['id' => 'verein-preview', 'edit' => true, 'options' => ['style' => 'margin-top:10px']]) ?>
            <p class="help-block">Bild (PNG/JPG), am besten quadratisch.</p>
        </div>

        <?php $gallery = $isNew ? [] : $verein->getGallery(); $credits = $isNew ? [] : $verein->getBildnachweise(); ?>
        <div class="form-group">
            <label>Bilder</label>
            <?php if ($gallery): ?>
                <div style="display:flex;flex-wrap:wrap;gap:10px;margin-bottom:8px">
                    <?php foreach ($gallery as $file): ?>
                        <label style="font-weight:normal;width:140px">
                            <?= Html::img($file->getUrl(), ['alt' => '', 'style' => 'width:140px;height:100px;object-fit:cover;border-radius:8px;display:block']) ?>
                            <?= Html::checkbox('bildEntfernen[]', false, ['value' => $file->guid]) ?> entfernen
                            <?php if (isset($credits[$file->guid])): ?><br><small class="text-muted"><?= Html::encode($credits[$file->guid]['urheber'] ?? '') ?></small><?php endif ?>
                        </label>
                    <?php endforeach ?>
                </div>
            <?php endif ?>
            <?php if (count($gallery) < Verein::MAX_BILDER): ?>
                <?= UploadButton::widget([
                    'id' => 'verein-galerie',
                    'submitName' => 'galerieList[]',
                    'label' => 'Bilder hinzufügen',
                    'tooltip' => false,
                    'cssButtonClass' => 'btn-light',
                    'progress' => '#verein-galerie-progress',
                    'preview' => '#verein-galerie-preview',
                    'max' => Verein::MAX_BILDER - count($gallery),
                ]) ?>
                <?= UploadProgress::widget(['id' => 'verein-galerie-progress']) ?>
                <?= FilePreview::widget(['id' => 'verein-galerie-preview', 'edit' => true, 'options' => ['style' => 'margin-top:10px']]) ?>
            <?php endif ?>
            <p class="help-block">Bis zu <?= Verein::MAX_BILDER ?> Bilder, z. B. Vereinsheim, Training, Feste. Nur eigene Fotos oder Fotos mit Erlaubnis;
                bei Personen bitte deren Einverständnis.</p>
        </div>

        <?php if ($isNew && !Module::canManageAll()): ?>
            <div class="form-group">
                <label for="verein-nachricht">Ihre Funktion im Verein</label>
                <?= Html::textInput('nachricht', $nachricht, ['id' => 'verein-nachricht', 'class' => 'form-control', 'maxlength' => 1000,
                    'placeholder' => 'z. B. 1. Vorsitzender, Kassiererin', 'required' => true]) ?>
            </div>
        <?php endif ?>

        <button type="submit" class="btn btn-primary"><?= $isNew ? 'Vorschlag absenden' : 'Speichern' ?></button>
        <?= Html::a('Abbrechen', $isNew ? $space->createUrl('/vereine/verein/index') : $verein->getUrl(), ['class' => 'btn btn-link']) ?>
        <?php ActiveForm::end() ?>
    </div>
</div>
