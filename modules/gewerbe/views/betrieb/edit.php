<?php
/**
 * @var \humhub\modules\gewerbe\models\Betrieb $betrieb
 * @var bool $isNew
 * @var string $nachricht
 * @var \humhub\modules\gewerbe\Module $module
 */

use humhub\helpers\Html;
use humhub\modules\content\widgets\richtext\RichTextField;
use humhub\modules\file\widgets\FilePreview;
use humhub\modules\file\widgets\UploadButton;
use humhub\modules\file\widgets\UploadProgress;
use humhub\modules\gewerbe\components\Oeffnungszeiten;
use humhub\modules\gewerbe\models\Betrieb;
use humhub\modules\gewerbe\Module;
use humhub\widgets\form\ActiveForm;

$space = $this->context->contentContainer;
$logo = $isNew ? null : $betrieb->getLogo();
$socialRows = array_values($betrieb->socialRows);
for ($i = count($socialRows); $i < min(Betrieb::MAX_SOCIAL, count($socialRows) + 1); $i++) {
    $socialRows[] = ['typ' => '', 'url' => ''];
}
?>
<div class="panel panel-default">
    <div class="panel-heading"><strong><?= $isNew ? 'Betrieb eintragen' : 'Betriebsseite bearbeiten' ?></strong></div>
    <div class="panel-body">
        <?php if ($isNew && !Module::canManageAll()): ?>
            <p class="help-block">Der Eintrag ist kostenlos. Wir prüfen kurz, ob du zum Betrieb gehörst. Danach erscheint er im Verzeichnis und du kannst ihn jederzeit selbst ändern.</p>
        <?php endif ?>
        <p class="help-block">Bitte nur geschäftliche Angaben, die öffentlich sein dürfen (Geschäftsadresse, Geschäftstelefon, info@…).</p>

        <?php $form = ActiveForm::begin(['id' => 'gw-form']) ?>
        <?= $form->field($betrieb, 'name')->textInput(['maxlength' => 150, 'readonly' => !$isNew && !Module::canManageAll()]) ?>
        <div class="row">
            <div class="col-sm-6"><?= $form->field($betrieb, 'kategorie')->dropDownList(array_combine($module->categories, $module->categories), ['prompt' => 'Bitte wählen …']) ?></div>
            <div class="col-sm-6"><?= $form->field($betrieb, 'ortsteil')->dropDownList(array_combine($module->ortsteile, $module->ortsteile)) ?></div>
        </div>
        <?= $form->field($betrieb, 'angebot')->textInput(['maxlength' => 500, 'placeholder' => 'z. B. Brot, Torten, Catering, Frühstück'])
            ->hint('Mit Komma getrennt; danach wird auch gesucht.') ?>
        <?= $form->field($betrieb, 'adresse')->textInput(['placeholder' => 'z. B. Marktplatz 1, 97215 Uffenheim', 'autocomplete' => 'street-address']) ?>
        <fieldset class="form-group gw-zeiten-form">
            <legend style="font-size:1rem;font-weight:600;margin-bottom:4px">Öffnungszeiten</legend>
            <p class="help-block" style="margin-top:0">Je Tag bis zu zwei Zeitspannen, z. B. 08:00–12:00 und 14:00–18:00. Leer = geschlossen.
                Daraus zeigen wir „Jetzt geöffnet“ an.</p>
            <?php foreach (Oeffnungszeiten::TAGE as $tag => $name): $r = array_pad(array_values((array)($betrieb->zeitenRows[$tag] ?? [])), 4, ''); ?>
                <div class="gw-tag">
                    <span class="gw-tagname" id="gw-tag-<?= $tag ?>"><?= $name ?></span>
                    <?php foreach ([0 => 'von', 1 => 'bis', 2 => 'von', 3 => 'bis'] as $i => $art): ?>
                        <?php if ($i === 2): ?><span class="gw-und" aria-hidden="true">und</span><?php endif ?>
                        <?= Html::input('time', "Betrieb[zeitenRows][$tag][$i]", $r[$i], ['class' => 'form-control', 'step' => 300,
                            'aria-label' => "$name, " . ($i < 2 ? 'erste' : 'zweite') . " Spanne, $art"]) ?>
                    <?php endforeach ?>
                </div>
            <?php endforeach ?>
            <?= Html::error($betrieb, 'zeitenRows', ['class' => 'text-danger']) ?>
        </fieldset>
        <?= $form->field($betrieb, 'oeffnungszeiten')->textarea(['rows' => 2, 'maxlength' => 1000,
            'placeholder' => 'z. B. Betriebsferien 1.–15. August, Termine nach Vereinbarung']) ?>
        <div class="row">
            <div class="col-sm-4"><?= $form->field($betrieb, 'telefon')->textInput(['type' => 'tel', 'autocomplete' => 'tel']) ?></div>
            <div class="col-sm-4"><?= $form->field($betrieb, 'email')->textInput(['type' => 'email', 'placeholder' => 'info@…']) ?></div>
            <div class="col-sm-4"><?= $form->field($betrieb, 'website')->textInput(['placeholder' => 'https://…']) ?></div>
        </div>
        <?= $form->field($betrieb, 'beschreibung')->widget(RichTextField::class, [
            'placeholder' => 'Wer seid ihr, was bietet ihr an, was macht euch besonders?',
            'exclude' => ['mention', 'oembed', 'upload'],
            'pluginOptions' => ['maxHeight' => '400px'],
        ]) ?>

        <fieldset class="form-group">
            <legend style="font-size:1rem;font-weight:600;margin-bottom:6px">Social Media / weitere Links</legend>
            <?php foreach ($socialRows as $i => $row): ?>
                <div class="row" style="margin-bottom:6px">
                    <div class="col-sm-3"><?= Html::textInput("Betrieb[socialRows][$i][typ]", $row['typ'] ?? '', ['class' => 'form-control', 'placeholder' => 'z. B. Instagram', 'aria-label' => 'Art des Links ' . ($i + 1)]) ?></div>
                    <div class="col-sm-9"><?= Html::textInput("Betrieb[socialRows][$i][url]", $row['url'] ?? '', ['class' => 'form-control', 'placeholder' => 'https://…', 'aria-label' => 'Adresse des Links ' . ($i + 1)]) ?></div>
                </div>
            <?php endforeach ?>
            <?= Html::error($betrieb, 'socialRows', ['class' => 'text-danger']) ?>
        </fieldset>

        <div class="form-group">
            <label>Logo</label>
            <?php if ($logo): ?>
                <div style="margin-bottom:8px;display:flex;align-items:center;gap:12px">
                    <?= Html::img($logo->getUrl([], false), ['alt' => 'Aktuelles Logo', 'style' => 'height:64px;border-radius:10px']) ?>
                    <label style="font-weight:normal"><?= Html::checkbox('logoEntfernen', false) ?> Logo entfernen</label>
                </div>
            <?php endif ?>
            <?= UploadButton::widget([
                'id' => 'gw-upload',
                'label' => $logo ? 'Anderes Logo hochladen' : 'Logo hochladen',
                'tooltip' => false,
                'cssButtonClass' => 'btn-light',
                'progress' => '#gw-progress',
                'preview' => '#gw-preview',
                'max' => 1,
            ]) ?>
            <?= UploadProgress::widget(['id' => 'gw-progress']) ?>
            <?= FilePreview::widget(['id' => 'gw-preview', 'edit' => true, 'options' => ['style' => 'margin-top:10px']]) ?>
            <p class="help-block">Bild (PNG/JPG), am besten quadratisch.</p>
        </div>

        <?php $gallery = $isNew ? [] : $betrieb->getGallery(); ?>
        <div class="form-group">
            <label>Bilder</label>
            <?php if ($gallery): ?>
                <div style="display:flex;flex-wrap:wrap;gap:10px;margin-bottom:8px">
                    <?php foreach ($gallery as $file): ?>
                        <label style="font-weight:normal;width:140px">
                            <?= Html::img($file->getUrl([], false), ['alt' => '', 'style' => 'width:140px;height:100px;object-fit:cover;border-radius:8px;display:block']) ?>
                            <?= Html::checkbox('bildEntfernen[]', false, ['value' => $file->guid]) ?> entfernen
                        </label>
                    <?php endforeach ?>
                </div>
            <?php endif ?>
            <?php if (count($gallery) < Betrieb::MAX_BILDER): ?>
                <?= UploadButton::widget([
                    'id' => 'gw-galerie',
                    'submitName' => 'galerieList[]',
                    'label' => 'Bilder hinzufügen',
                    'tooltip' => false,
                    'cssButtonClass' => 'btn-light',
                    'progress' => '#gw-galerie-progress',
                    'preview' => '#gw-galerie-preview',
                    'max' => Betrieb::MAX_BILDER - count($gallery),
                ]) ?>
                <?= UploadProgress::widget(['id' => 'gw-galerie-progress']) ?>
                <?= FilePreview::widget(['id' => 'gw-galerie-preview', 'edit' => true, 'options' => ['style' => 'margin-top:10px']]) ?>
            <?php endif ?>
            <p class="help-block">Bis zu <?= Betrieb::MAX_BILDER ?> Bilder, z. B. Laden, Produkte, Werkstatt. Nur eigene Fotos;
                bei Personen bitte deren Einverständnis.</p>
        </div>

        <?php if ($isNew && !Module::canManageAll()): ?>
            <div class="form-group">
                <label for="gw-nachricht">Deine Funktion im Betrieb</label>
                <?= Html::textInput('nachricht', $nachricht, ['id' => 'gw-nachricht', 'class' => 'form-control', 'maxlength' => 1000,
                    'placeholder' => 'z. B. Inhaberin, Geschäftsführer', 'required' => true]) ?>
            </div>
        <?php endif ?>

        <button type="submit" class="btn btn-primary"><?= $isNew ? 'Eintrag absenden' : 'Speichern' ?></button>
        <?= Html::a('Abbrechen', $isNew ? $space->createUrl('/gewerbe/betrieb/index') : $betrieb->getUrl(), ['class' => 'btn btn-link']) ?>
        <?php ActiveForm::end() ?>
    </div>
</div>
<style <?= Html::nonce() ?>>
    .gw-tag { display: grid; grid-template-columns: 96px 1fr 1fr auto 1fr 1fr; gap: 6px; align-items: center; margin-bottom: 6px; }
    .gw-tagname { font-weight: 600; }
    .gw-und { font-size: .9em; }
    @media (max-width: 575.98px) {
        .gw-tag { grid-template-columns: 1fr 1fr; }
        .gw-tagname { grid-column: 1 / -1; margin-top: 4px; }
        .gw-und { display: none; }
    }
</style>
