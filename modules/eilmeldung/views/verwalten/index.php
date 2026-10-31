<?php
/**
 * @var \humhub\modules\eilmeldung\models\Eilmeldung $model
 * @var int $dauer
 * @var \humhub\modules\eilmeldung\models\Eilmeldung[] $aktiv
 * @var \humhub\modules\eilmeldung\Module $module
 * @var int $empfaenger
 */

use humhub\modules\eilmeldung\controllers\VerwaltenController;
use humhub\modules\eilmeldung\Module;
use yii\helpers\Html;

$this->title = Yii::t('EilmeldungModule.base', 'Eilmeldung schreiben');
$t = fn($s, $p = []) => Yii::t('EilmeldungModule.base', $s, $p);
?>
<div class="container">
    <div class="panel panel-default">
        <div class="panel-heading"><strong><?= Html::encode($this->title) ?></strong>
            <?= Html::a($t('Alle Eilmeldungen'), ['/eilmeldung/index'], ['class' => 'float-end pull-right small']) ?></div>
        <div class="panel-body">
            <p><?= $t('Für Dinge, die viele in Uffenheim sofort wissen müssen: Straßensperrung, Wasserrohrbruch oder Abschaltung, Stromausfall, Unwetterschäden. Die Meldung steht oben auf der Startseite, bis sie abläuft oder du sie beendest, und geht als Push-Nachricht an {n} Konten.', ['n' => $empfaenger]) ?></p>
            <?php if ($module->empfaenger !== null): ?>
                <p class="alert alert-info"><?= $t('Testbetrieb: Die Push-Nachricht geht vorerst nur an {liste}.', ['liste' => Html::encode(implode(', ', $module->empfaenger))]) ?></p>
            <?php endif ?>
            <?= Html::beginForm(['index'], 'post') ?>
            <div class="mb-3 form-group">
                <label class="control-label" for="em-art"><?= $model->getAttributeLabel('art') ?></label>
                <?= Html::activeDropDownList($model, 'art', array_map(fn($k) => $module->artName($k), array_combine(Module::artenVonHand(), Module::artenVonHand())), ['class' => 'form-control form-select', 'id' => 'em-art']) ?>
            </div>
            <div class="mb-3 form-group<?= $model->hasErrors('titel') ? ' has-error' : '' ?>">
                <label class="control-label" for="em-titel"><?= $model->getAttributeLabel('titel') ?></label>
                <?= Html::activeTextInput($model, 'titel', ['class' => 'form-control', 'id' => 'em-titel', 'maxlength' => 150, 'required' => true,
                    'placeholder' => $t('z. B. Würzburger Straße zwischen Marktplatz und Bahnhof gesperrt')]) ?>
                <?= Html::error($model, 'titel', ['class' => 'help-block']) ?>
            </div>
            <div class="mb-3 form-group">
                <label class="control-label" for="em-text"><?= $model->getAttributeLabel('text') ?></label>
                <?= Html::activeTextarea($model, 'text', ['class' => 'form-control', 'id' => 'em-text', 'rows' => 4, 'maxlength' => 2000]) ?>
            </div>
            <div class="mb-3 form-group">
                <label class="control-label" for="em-dauer"><?= $model->getAttributeLabel('gueltig_bis') ?></label>
                <?= Html::dropDownList('dauer', $dauer, array_combine(VerwaltenController::DAUER, array_map(fn($h) => $h < 48
                    ? $t('{n} Stunden', ['n' => $h]) : $t('{n} Tage', ['n' => $h / 24]), VerwaltenController::DAUER)), ['class' => 'form-control form-select', 'id' => 'em-dauer']) ?>
            </div>
            <div class="checkbox" style="margin-bottom:14px">
                <label><?= Html::checkbox('push', true) ?> <?= $t('Als Push-Nachricht schicken') ?></label>
            </div>
            <button type="submit" class="btn btn-primary"><?= $t('Veröffentlichen') ?></button>
            <?= Html::endForm() ?>

            <?php if ($aktiv): ?>
                <hr>
                <strong><?= $t('Laufende Eilmeldungen') ?></strong>
                <ul class="list-unstyled" style="margin-top:6px">
                    <?php foreach ($aktiv as $e): ?>
                        <li style="padding:6px 0;border-bottom:1px solid #eee">
                            <?= Html::encode($module->artName($e->art) . ': ' . $e->titel) ?>
                            <span class="text-muted small">(<?= Html::encode($t('bis {zeit}', ['zeit' => date('j.n., G:i', strtotime($e->gueltig_bis))]))
                                . ($e->quelle === 'nina' ? ', NINA' : '') . ($e->empfaenger ? ', ' . Html::encode($t('Push an {n}', ['n' => $e->empfaenger])) : '') ?>)</span>
                            <?= Html::beginForm(['beenden', 'id' => $e->id], 'post', ['style' => 'display:inline']) ?>
                            <button type="submit" class="btn btn-light btn-sm"><?= $t('Beenden') ?></button>
                            <?= Html::endForm() ?>
                        </li>
                    <?php endforeach ?>
                </ul>
            <?php endif ?>
        </div>
    </div>
</div>
