<?php
/**
 * @var \humhub\modules\whatsapp\models\Entwurf $entwurf
 * @var \humhub\modules\calendar\models\CalendarEntry[] $aehnliche
 * @var \humhub\modules\calendar\models\CalendarEntryType[] $typen
 * @var \humhub\modules\whatsapp\models\Nachricht[] $nachrichten
 */

use humhub\helpers\Html;
use humhub\modules\content\widgets\richtext\RichTextField;
use humhub\widgets\form\ActiveForm;

$space = $this->context->contentContainer;
$kategorien = ['' => '– keine –'];
foreach ($typen as $t) {
    $kategorien[$t->name] = $t->name;
}
?>
<div class="panel panel-default">
    <div class="panel-heading"><strong>Termin aus der WhatsApp-Community prüfen</strong></div>
    <div class="panel-body">
        <div class="row">
            <div class="col-md-5" style="margin-bottom:14px">
                <?php foreach ($nachrichten as $n): ?>
                    <?php if ($n->isBild()): ?>
                        <?= Html::a(Html::img($space->createUrl('/whatsapp/eingang/datei', ['id' => $n->id]),
                            ['style' => 'max-width:100%;border-radius:8px;margin-bottom:8px', 'alt' => 'Eingereichtes Bild']),
                            $space->createUrl('/whatsapp/eingang/datei', ['id' => $n->id]), ['target' => '_blank']) ?>
                    <?php elseif ($n->datei): ?>
                        <p><?= Html::a('<i class="fa fa-file-pdf-o"></i> PDF öffnen', $space->createUrl('/whatsapp/eingang/datei', ['id' => $n->id]), ['target' => '_blank']) ?></p>
                    <?php endif ?>
                    <?php if ($n->text): ?>
                        <div class="text-muted" style="white-space:pre-line;margin-bottom:8px"><?= Html::encode($n->text) ?></div>
                    <?php endif ?>
                <?php endforeach ?>
                <small class="text-muted">Eingereicht von <?= Html::encode($entwurf->absender_name ?: 'unbekannt') ?>
                    <?= $entwurf->wa_id ? '(+' . Html::encode($entwurf->wa_id) . ')' : '' ?>,
                    <?= Yii::$app->formatter->asDatetime($entwurf->created_at, 'short') ?></small>
            </div>
            <div class="col-md-7">
                <?php if ($entwurf->hinweis): ?>
                    <div class="alert alert-info"><strong>Hinweis der KI:</strong> <?= Html::encode($entwurf->hinweis) ?></div>
                <?php endif ?>

                <?php $f = ActiveForm::begin(['id' => 'entwurf-form']) ?>
                <?php if ($aehnliche): ?>
                    <div class="alert alert-warning">
                        <strong>Gibt es diesen Termin schon?</strong> Am selben Tag steht im Kalender:
                        <ul style="margin:6px 0">
                            <?php foreach ($aehnliche as $e): ?>
                                <li><?= Html::a(Html::encode($e->title), $e->content->getUrl(), ['target' => '_blank']) ?> (<?= Html::encode($e->getFormattedTime()) ?>)</li>
                            <?php endforeach ?>
                        </ul>
                        Wenn es derselbe Termin ist, bitte ablehnen.
                        <div class="checkbox" style="margin-bottom:0"><label><?= Html::activeCheckbox($entwurf, 'trotzdem', ['label' => false]) ?> Es ist ein anderer Termin, trotzdem übernehmen</label></div>
                    </div>
                <?php endif ?>

                <?= $f->field($entwurf, 'titel')->textInput(['maxlength' => 200]) ?>
                <?= $f->field($entwurf, 'ganztaegig')->checkbox(['id' => 'entwurf-ganztaegig']) ?>
                <div class="row">
                    <div class="col-7 col-sm-4"><?= $f->field($entwurf, 'start_datum')->input('date') ?></div>
                    <div class="col-5 col-sm-2 termin-zeit"><?= $f->field($entwurf, 'start_zeit')->input('time') ?></div>
                    <div class="col-7 col-sm-4"><?= $f->field($entwurf, 'ende_datum')->input('date')->hint('leer = gleicher Tag') ?></div>
                    <div class="col-5 col-sm-2 termin-zeit"><?= $f->field($entwurf, 'ende_zeit')->input('time')->hint('leer = 2 Std.') ?></div>
                </div>
                <?= $f->field($entwurf, 'ort')->textInput(['maxlength' => 255]) ?>
                <?= $f->field($entwurf, 'veranstalter')->textInput(['maxlength' => 200]) ?>
                <?= $f->field($entwurf, 'kategorie')->dropDownList($kategorien) ?>
                <?= $f->field($entwurf, 'beschreibung')->widget(RichTextField::class, [
                    'exclude' => ['mention', 'oembed', 'upload'],
                    'pluginOptions' => ['maxHeight' => '300px'],
                ])->hint('Das Plakat wird als Bild angehängt. Unter dem Text steht automatisch „Eingereicht über die WhatsApp-Community“.') ?>

                <button type="submit" class="btn btn-primary"><i class="fa fa-check"></i> In den Kalender übernehmen</button>
                <?= Html::a('Zurück', $space->createUrl('/whatsapp/eingang/index'), ['class' => 'btn btn-link']) ?>
                <?= Html::a('<i class="fa fa-times"></i> Ablehnen', $space->createUrl('/whatsapp/eingang/ablehnen', ['id' => $entwurf->id]), [
                    'class' => 'btn btn-danger float-end', 'data-method' => 'post', 'data-confirm' => 'Diesen Entwurf ablehnen? Er erscheint dann nicht im Kalender.']) ?>
                <?php ActiveForm::end() ?>
            </div>
        </div>
    </div>
</div>
<script <?= Html::nonce() ?>>
(function () {
    var box = document.getElementById('entwurf-ganztaegig');
    if (!box) return;
    function toggle() {
        document.querySelectorAll('.termin-zeit').forEach(function (el) { el.style.display = box.checked ? 'none' : ''; });
    }
    box.addEventListener('change', toggle);
    toggle();
})();
</script>
