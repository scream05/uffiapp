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
    <div class="panel-heading"><strong><?= $isNew ? 'Termin eintragen' : 'Termin bearbeiten' ?>: <?= Html::encode($verein->name) ?></strong></div>
    <div class="panel-body">
        <?php if ($isNew): ?>
            <p class="help-block">Der Termin erscheint im gemeinsamen Kalender unter „Veranstaltungen“ und auf der Seite Ihres Vereins.
                Alle Verwalter des Vereins können ihn später ändern.</p>
        <?php endif ?>

        <?php $f = ActiveForm::begin(['id' => 'termin-form']) ?>
        <?php if ($similar): ?>
            <div class="alert alert-warning">
                <strong>Gibt es diesen Termin schon?</strong> Die Stadt hat am selben Tag folgende Veranstaltung im Kalender:
                <ul style="margin:6px 0">
                    <?php foreach ($similar as $e): ?>
                        <li><?= Html::a(Html::encode($e->title), $e->content->getUrl(), ['target' => '_blank']) ?> (<?= Html::encode($e->getFormattedTime()) ?>)</li>
                    <?php endforeach ?>
                </ul>
                Wenn es derselbe Termin ist, brauchen Sie ihn nicht noch einmal einzutragen.
                <div class="checkbox" style="margin-bottom:0"><label><?= Html::activeCheckbox($form, 'trotzdem', ['label' => false]) ?> Es ist ein anderer Termin, trotzdem eintragen</label></div>
            </div>
        <?php endif ?>

        <?= $f->field($form, 'titel')->textInput(['maxlength' => 200, 'placeholder' => 'z. B. Sommerfest am Sportplatz']) ?>
        <?= $f->field($form, 'ganztaegig')->checkbox(['id' => 'termin-ganztaegig']) ?>
        <div class="row">
            <div class="col-xs-7 col-sm-4"><?= $f->field($form, 'start_datum')->input('date') ?></div>
            <div class="col-xs-5 col-sm-2 termin-zeit"><?= $f->field($form, 'start_zeit')->input('time') ?></div>
            <div class="col-xs-7 col-sm-4"><?= $f->field($form, 'ende_datum')->input('date')->hint('leer = gleicher Tag') ?></div>
            <div class="col-xs-5 col-sm-2 termin-zeit"><?= $f->field($form, 'ende_zeit')->input('time')->hint('leer = 2 Std.') ?></div>
        </div>
        <?= $f->field($form, 'ort')->textInput(['maxlength' => 255, 'placeholder' => 'z. B. Sportheim Uffenheim, Am Sportplatz 1']) ?>
        <?= $f->field($form, 'beschreibung')->widget(RichTextField::class, [
            'placeholder' => 'Programm, Eintritt, Anmeldung …',
            'exclude' => ['mention', 'oembed', 'upload'],
            'pluginOptions' => ['maxHeight' => '300px'],
        ]) ?>

        <button type="submit" class="btn btn-primary"><?= $isNew ? 'Termin eintragen' : 'Speichern' ?></button>
        <?= Html::a('Abbrechen', $verein->getUrl(), ['class' => 'btn btn-link']) ?>
        <?php if (!$isNew): ?>
            <?= Html::a('<i class="fa fa-trash"></i> Termin löschen', $space->createUrl('/vereine/termin/delete', ['id' => $form->entry->id]), [
                'class' => 'btn btn-danger pull-right', 'data-method' => 'post', 'data-confirm' => 'Diesen Termin wirklich löschen?']) ?>
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
