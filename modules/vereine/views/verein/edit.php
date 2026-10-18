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
    <div class="panel-heading"><strong><?= $isNew ? Yii::t('VereineModule.base', 'Verein vorschlagen') : Yii::t('VereineModule.base', 'Vereinsseite bearbeiten') ?></strong></div>
    <div class="panel-body">
        <?php if ($isNew && !Module::canManageAll()): ?>
            <p class="help-block"><?= Yii::t('VereineModule.base', 'Dein Vorschlag wird kurz geprüft. Danach erscheint der Verein im Verzeichnis und du kannst Seite und Termine pflegen.') ?></p>
        <?php endif ?>
        <p class="help-block"><?= Yii::t('VereineModule.base', 'Bitte nur Angaben, die öffentlich sein dürfen: allgemeine Kontaktdaten des Vereins (z. B. info@…), keine privaten Adressen oder Telefonnummern ohne Einverständnis der Person.') ?></p>

        <?php $form = ActiveForm::begin(['id' => 'verein-form']) ?>
        <?= $form->field($verein, 'name')->textInput(['maxlength' => 150, 'readonly' => !$isNew && !Module::canManageAll()]) ?>
        <div class="row">
            <div class="col-sm-6"><?= $form->field($verein, 'kategorie')->dropDownList(array_combine($module->categories, array_map([$module, 'kategorieLabel'], $module->categories)), ['prompt' => Yii::t('VereineModule.base', 'Bitte wählen …')]) ?></div>
            <div class="col-sm-6"><?= $form->field($verein, 'ortsteil')->dropDownList(array_combine($module->ortsteile, array_map([$module, 'ortLabel'], $module->ortsteile))) ?></div>
        </div>
        <?= $form->field($verein, 'beschreibung')->widget(RichTextField::class, [
            'placeholder' => Yii::t('VereineModule.base', 'Wer sind wir, was machen wir, wann trifft man uns, wie kann man mitmachen?'),
            'exclude' => ['mention', 'oembed', 'upload'],
            'pluginOptions' => ['maxHeight' => '400px'],
        ]) ?>
        <?= $form->field($verein, 'treffpunkt')->textInput(['placeholder' => Yii::t('VereineModule.base', 'z. B. Sportheim, Am Sportplatz 1, 97215 Uffenheim')]) ?>
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
            <label><?= Yii::t('VereineModule.base', 'Ansprechpartner') ?></label>
            <p class="help-block" style="margin-top:0"><?= Yii::t('VereineModule.base', 'Nur Personen, die damit einverstanden sind, auf der Vereinsseite genannt zu werden. Leere Zeilen werden ignoriert.') ?></p>
            <?php foreach ($apRows as $i => $row): ?>
                <div class="row verein-ap-row" style="margin-bottom:6px">
                    <div class="col-sm-3"><?= Html::textInput("Verein[apRows][$i][funktion]", $row['funktion'] ?? '', ['class' => 'form-control', 'placeholder' => Yii::t('VereineModule.base', 'Funktion, z. B. Jugendleiter'), 'aria-label' => Yii::t('VereineModule.base', 'Funktion')]) ?></div>
                    <div class="col-sm-3"><?= Html::textInput("Verein[apRows][$i][name]", $row['name'] ?? '', ['class' => 'form-control', 'placeholder' => Yii::t('VereineModule.base', 'Name'), 'aria-label' => Yii::t('VereineModule.base', 'Name')]) ?></div>
                    <div class="col-sm-3"><?= Html::textInput("Verein[apRows][$i][email]", $row['email'] ?? '', ['class' => 'form-control', 'type' => 'email', 'placeholder' => Yii::t('VereineModule.base', 'E-Mail (optional)'), 'aria-label' => Yii::t('VereineModule.base', 'E-Mail')]) ?></div>
                    <div class="col-sm-3"><?= Html::textInput("Verein[apRows][$i][telefon]", $row['telefon'] ?? '', ['class' => 'form-control', 'type' => 'tel', 'placeholder' => Yii::t('VereineModule.base', 'Telefon (optional)'), 'aria-label' => Yii::t('VereineModule.base', 'Telefon')]) ?></div>
                </div>
            <?php endforeach ?>
            <?= Html::error($verein, 'apRows', ['class' => 'text-danger']) ?>
            <?php if (count($apRows) < Verein::MAX_ANSPRECHPARTNER): ?><p class="help-block"><?= Yii::t('VereineModule.base', 'Mehr Zeilen erscheinen nach dem Speichern.') ?></p><?php endif ?>
        </div>

        <div class="form-group">
            <label><?= Yii::t('VereineModule.base', 'Social Media / weitere Links') ?></label>
            <?php foreach ($socialRows as $i => $row): ?>
                <div class="row" style="margin-bottom:6px">
                    <div class="col-sm-3"><?= Html::textInput("Verein[socialRows][$i][typ]", $row['typ'] ?? '', ['class' => 'form-control', 'placeholder' => Yii::t('VereineModule.base', 'z. B. Instagram'), 'aria-label' => Yii::t('VereineModule.base', 'Art')]) ?></div>
                    <div class="col-sm-9"><?= Html::textInput("Verein[socialRows][$i][url]", $row['url'] ?? '', ['class' => 'form-control', 'placeholder' => 'https://…', 'aria-label' => Yii::t('VereineModule.base', 'Adresse')]) ?></div>
                </div>
            <?php endforeach ?>
            <?= Html::error($verein, 'socialRows', ['class' => 'text-danger']) ?>
        </div>

        <div class="form-group">
            <label><?= Yii::t('VereineModule.base', 'Logo') ?></label>
            <?php if ($logo): ?>
                <div style="margin-bottom:8px;display:flex;align-items:center;gap:12px">
                    <?= Html::img($logo->getUrl(), ['alt' => Yii::t('VereineModule.base', 'Aktuelles Logo'), 'style' => 'height:64px;border-radius:10px']) ?>
                    <label style="font-weight:normal"><?= Html::checkbox('logoEntfernen', false) ?> <?= Yii::t('VereineModule.base', 'Logo entfernen') ?></label>
                </div>
            <?php endif ?>
            <?= UploadButton::widget([
                'id' => 'verein-upload',
                'label' => $logo ? Yii::t('VereineModule.base', 'Anderes Logo hochladen') : Yii::t('VereineModule.base', 'Logo hochladen'),
                'tooltip' => false,
                'cssButtonClass' => 'btn-light',
                'progress' => '#verein-progress',
                'preview' => '#verein-preview',
                'max' => 1,
            ]) ?>
            <?= UploadProgress::widget(['id' => 'verein-progress']) ?>
            <?= FilePreview::widget(['id' => 'verein-preview', 'edit' => true, 'options' => ['style' => 'margin-top:10px']]) ?>
            <p class="help-block"><?= Yii::t('VereineModule.base', 'Bild (PNG/JPG), am besten quadratisch.') ?></p>
        </div>

        <?php $gallery = $isNew ? [] : $verein->getGallery(); $credits = $isNew ? [] : $verein->getBildnachweise(); ?>
        <div class="form-group">
            <label><?= Yii::t('VereineModule.base', 'Bilder') ?></label>
            <?php if ($gallery): ?>
                <div style="display:flex;flex-wrap:wrap;gap:10px;margin-bottom:8px">
                    <?php foreach ($gallery as $file): ?>
                        <label style="font-weight:normal;width:140px">
                            <?= Html::img($file->getUrl(), ['alt' => '', 'style' => 'width:140px;height:100px;object-fit:cover;border-radius:8px;display:block']) ?>
                            <?= Html::checkbox('bildEntfernen[]', false, ['value' => $file->guid]) ?> <?= Yii::t('VereineModule.base', 'entfernen') ?>
                            <?php if (isset($credits[$file->guid])): ?><br><small class="text-muted"><?= Html::encode($credits[$file->guid]['urheber'] ?? '') ?></small><?php endif ?>
                        </label>
                    <?php endforeach ?>
                </div>
            <?php endif ?>
            <?php if (count($gallery) < Verein::MAX_BILDER): ?>
                <?= UploadButton::widget([
                    'id' => 'verein-galerie',
                    'submitName' => 'galerieList[]',
                    'label' => Yii::t('VereineModule.base', 'Bilder hinzufügen'),
                    'tooltip' => false,
                    'cssButtonClass' => 'btn-light',
                    'progress' => '#verein-galerie-progress',
                    'preview' => '#verein-galerie-preview',
                    'max' => Verein::MAX_BILDER - count($gallery),
                ]) ?>
                <?= UploadProgress::widget(['id' => 'verein-galerie-progress']) ?>
                <?= FilePreview::widget(['id' => 'verein-galerie-preview', 'edit' => true, 'options' => ['style' => 'margin-top:10px']]) ?>
            <?php endif ?>
            <p class="help-block"><?= Yii::t('VereineModule.base', 'Bis zu {n} Bilder, z. B. Vereinsheim, Training, Feste. Nur eigene Fotos oder Fotos mit Erlaubnis; bei Personen bitte deren Einverständnis.', ['n' => Verein::MAX_BILDER]) ?></p>
        </div>

        <?php if ($isNew && !Module::canManageAll()): ?>
            <div class="form-group">
                <label for="verein-nachricht"><?= Yii::t('VereineModule.base', 'Deine Funktion im Verein') ?></label>
                <?= Html::textInput('nachricht', $nachricht, ['id' => 'verein-nachricht', 'class' => 'form-control', 'maxlength' => 1000,
                    'placeholder' => Yii::t('VereineModule.base', 'z. B. 1. Vorsitzender, Kassiererin'), 'required' => true]) ?>
            </div>
        <?php endif ?>

        <button type="submit" class="btn btn-primary"><?= $isNew ? Yii::t('VereineModule.base', 'Vorschlag absenden') : Yii::t('VereineModule.base', 'Speichern') ?></button>
        <?= Html::a(Yii::t('VereineModule.base', 'Abbrechen'), $isNew ? $space->createUrl('/vereine/verein/index') : $verein->getUrl(), ['class' => 'btn btn-link']) ?>
        <?php ActiveForm::end() ?>
    </div>
</div>
