<?php
/**
 * @var \humhub\modules\vereine\models\TerminForm $form
 * @var \humhub\modules\vereine\models\Verein $verein
 * @var \humhub\modules\calendar\models\CalendarEntry[] $similar
 */

use humhub\helpers\Html;
use humhub\modules\content\widgets\richtext\RichTextField;
use humhub\widgets\form\ActiveForm;

$space = $this->context->contentContainer;
$isNew = $form->entry->isNewRecord;
?>
<div class="panel panel-default">
    <div class="panel-heading"><strong><?= $isNew ? Yii::t('VereineModule.base', 'Termin eintragen') : Yii::t('VereineModule.base', 'Termin bearbeiten') ?>: <?= Html::encode($verein->name) ?></strong></div>
    <div class="panel-body">
        <?php if ($isNew): ?>
            <p class="help-block"><?= Yii::t('VereineModule.base', 'Der Termin erscheint im gemeinsamen Kalender unter „Veranstaltungen“ und auf der Seite deines Vereins. Alle Verwalter des Vereins können ihn später ändern.') ?></p>
        <?php endif ?>

        <?php $f = ActiveForm::begin(['id' => 'termin-form']) ?>
        <?php if ($similar): ?>
            <div class="alert alert-warning">
                <strong><?= Yii::t('VereineModule.base', 'Gibt es diesen Termin schon?') ?></strong> <?= Yii::t('VereineModule.base', 'Die Stadt hat am selben Tag folgende Veranstaltung im Kalender:') ?>
                <ul style="margin:6px 0">
                    <?php foreach ($similar as $e): ?>
                        <li><?= Html::a(Html::encode($e->title), $e->content->getUrl(), ['target' => '_blank']) ?> (<?= Html::encode($e->getFormattedTime()) ?>)</li>
                    <?php endforeach ?>
                </ul>
                <?= Yii::t('VereineModule.base', 'Wenn es derselbe Termin ist, brauchst du ihn nicht noch einmal einzutragen.') ?>
                <div class="checkbox" style="margin-bottom:0"><label><?= Html::activeCheckbox($form, 'trotzdem', ['label' => false]) ?> <?= Yii::t('VereineModule.base', 'Es ist ein anderer Termin, trotzdem eintragen') ?></label></div>
            </div>
        <?php endif ?>

        <?= $f->field($form, 'titel')->textInput(['maxlength' => 200, 'placeholder' => Yii::t('VereineModule.base', 'z. B. Sommerfest am Sportplatz')]) ?>
        <?= $f->field($form, 'ganztaegig')->checkbox(['id' => 'termin-ganztaegig']) ?>
        <div class="row">
            <div class="col-xs-7 col-sm-4"><?= $f->field($form, 'start_datum')->input('date') ?></div>
            <div class="col-xs-5 col-sm-2 termin-zeit"><?= $f->field($form, 'start_zeit')->input('time') ?></div>
            <div class="col-xs-7 col-sm-4"><?= $f->field($form, 'ende_datum')->input('date')->hint(Yii::t('VereineModule.base', 'leer = gleicher Tag')) ?></div>
            <div class="col-xs-5 col-sm-2 termin-zeit"><?= $f->field($form, 'ende_zeit')->input('time')->hint(Yii::t('VereineModule.base', 'leer = 2 Std.')) ?></div>
        </div>
        <?= $f->field($form, 'ort')->textInput(['maxlength' => 255, 'placeholder' => Yii::t('VereineModule.base', 'z. B. Sportheim Uffenheim, Am Sportplatz 1')]) ?>
        <?= $f->field($form, 'beschreibung')->widget(RichTextField::class, [
            'placeholder' => Yii::t('VereineModule.base', 'Programm, Eintritt, Anmeldung …'),
            'exclude' => ['mention', 'oembed', 'upload'],
            'pluginOptions' => ['maxHeight' => '300px'],
        ]) ?>

        <button type="submit" class="btn btn-primary"><?= $isNew ? Yii::t('VereineModule.base', 'Termin eintragen') : Yii::t('VereineModule.base', 'Speichern') ?></button>
        <?= Html::a(Yii::t('VereineModule.base', 'Abbrechen'), $verein->getUrl(), ['class' => 'btn btn-link']) ?>
        <?php if (!$isNew): ?>
            <?= Html::a('<i class="fa fa-trash"></i> ' . Yii::t('VereineModule.base', 'Termin löschen'), $space->createUrl('/vereine/termin/delete', ['id' => $form->entry->id]), [
                'class' => 'btn btn-danger pull-right', 'data-method' => 'post', 'data-confirm' => Yii::t('VereineModule.base', 'Diesen Termin wirklich löschen?')]) ?>
        <?php endif ?>
        <?php ActiveForm::end() ?>
    </div>
</div>
<script <?= Html::nonce() ?>>
(function () {
    var box = document.getElementById('termin-ganztaegig');
    if (!box) return;
    function toggle() {
        document.querySelectorAll('.termin-zeit').forEach(function (el) { el.style.display = box.checked ? 'none' : ''; });
    }
    box.addEventListener('change', toggle);
    toggle();
})();
</script>
