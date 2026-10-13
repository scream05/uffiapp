<?php
/**
 * @var \humhub\modules\space\models\Space $space
 * @var \humhub\modules\maengelmelder\models\Mitarbeiter $model
 * @var \humhub\modules\maengelmelder\models\Mitarbeiter[] $liste
 * @var array $offen [mitarbeiter_id => Anzahl offener Aufträge]
 * @var array $ungelesen [mitarbeiter_id => Anzahl ungelesener Nachrichten]
 * @var \humhub\modules\maengelmelder\Module $module
 */

use humhub\helpers\Html;

$fmt = Yii::$app->formatter;
?>
<div class="panel panel-default">
    <div class="panel-body">
        <?= $this->render('_nav', ['space' => $space, 'aktiv' => 'mitarbeiter']) ?>

        <?php if ($liste): ?>
            <table class="table table-sm align-middle">
                <thead><tr><th>Name</th><th>Handynummer</th><th>Offene Aufträge</th><th>Letzte Nachricht</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($liste as $m): ?>
                    <tr class="<?= $m->aktiv ? '' : 'text-muted' ?>">
                        <td><?= Html::encode($m->name) ?><?= $m->aktiv ? '' : ' <small>(inaktiv)</small>' ?>
                            <?php if (!empty($ungelesen[$m->id])): ?><span class="bauhof-neu"><?= (int)$ungelesen[$m->id] ?></span><?php endif ?></td>
                        <td><?= Html::encode($m->getTelefonAnzeige()) ?></td>
                        <td><?= (int)($offen[$m->id] ?? 0) ?></td>
                        <td><small><?= $m->letzte_eingang_at ? $fmt->asRelativeTime($m->letzte_eingang_at) : 'noch nie' ?>
                                <?= $m->isFensterOffen() ? ' <span class="text-success" title="Freie Nachrichten möglich">●</span>' : '' ?></small></td>
                        <td class="text-end text-nowrap">
                            <?= Html::a('<i class="fa fa-comments"></i>', $space->createUrl('/maengelmelder/bauhof/chat', ['id' => $m->id]), ['class' => 'btn btn-sm btn-light', 'title' => 'Nachrichten']) ?>
                            <?= Html::a('<i class="fa fa-pencil"></i>', $space->createUrl('/maengelmelder/bauhof/mitarbeiter', ['id' => $m->id]), ['class' => 'btn btn-sm btn-light', 'title' => 'Bearbeiten']) ?>
                            <?= Html::beginForm($space->createUrl('/maengelmelder/bauhof/mitarbeiter-loeschen', ['id' => $m->id]), 'post', ['class' => 'd-inline bauhof-ma-loeschen']) ?>
                            <button class="btn btn-sm btn-light text-danger" title="Löschen"><i class="fa fa-trash"></i></button>
                            <?= Html::endForm() ?>
                        </td>
                    </tr>
                <?php endforeach ?>
                </tbody>
            </table>
        <?php else: ?>
            <p class="text-muted">Noch keine Mitarbeiter eingetragen.</p>
        <?php endif ?>

        <h3 class="h6 mt-4"><?= $model->isNewRecord ? 'Mitarbeiter hinzufügen' : 'Mitarbeiter bearbeiten: ' . Html::encode($model->getOldAttribute('name')) ?></h3>
        <?= Html::beginForm($model->isNewRecord ? $space->createUrl('/maengelmelder/bauhof/mitarbeiter')
            : $space->createUrl('/maengelmelder/bauhof/mitarbeiter', ['id' => $model->id]), 'post', ['class' => 'card card-body', 'style' => 'max-width:560px']) ?>
        <?= Html::errorSummary($model, ['class' => 'alert alert-danger']) ?>
        <div class="mb-2">
            <?= Html::activeLabel($model, 'name', ['class' => 'form-label']) ?>
            <?= Html::activeTextInput($model, 'name', ['class' => 'form-control', 'maxlength' => 100, 'placeholder' => 'z. B. Max M.']) ?>
        </div>
        <div class="mb-2">
            <?= Html::activeLabel($model, 'telefon', ['class' => 'form-label']) ?>
            <?= Html::activeTextInput($model, 'telefon', ['class' => 'form-control', 'inputmode' => 'tel', 'placeholder' => '0151 23456789',
                'value' => $model->telefon && !$model->hasErrors('telefon') ? $model->getTelefonAnzeige() : $model->telefon]) ?>
        </div>
        <?php if (!$model->isNewRecord): ?>
            <div class="form-check mb-2">
                <?= Html::activeCheckbox($model, 'aktiv', ['class' => 'form-check-input', 'label' => null, 'id' => 'ma-aktiv']) ?>
                <label class="form-check-label" for="ma-aktiv">Aktiv (bekommt Aufträge; inaktive Nummern landen wieder im normalen Eingang)</label>
            </div>
        <?php else: ?>
            <div class="form-check mb-2">
                <?= Html::activeCheckbox($model, 'einwilligung', ['class' => 'form-check-input', 'label' => null, 'id' => 'ma-einwilligung']) ?>
                <label class="form-check-label small" for="ma-einwilligung"><?= Html::encode($model->getAttributeLabel('einwilligung')) ?></label>
            </div>
        <?php endif ?>
        <div>
            <button class="btn btn-primary"><i class="fa fa-save"></i> Speichern</button>
            <?php if (!$model->isNewRecord): ?>
                <?= Html::a('Abbrechen', $space->createUrl('/maengelmelder/bauhof/mitarbeiter'), ['class' => 'btn btn-light']) ?>
            <?php endif ?>
        </div>
        <?= Html::endForm() ?>

        <div class="alert alert-info mt-3 small" style="max-width:560px">
            <strong>So kommt ein neuer Mitarbeiter dazu:</strong> Nummer hier eintragen, dann schreibt er einmal „Hallo“ an
            die WhatsApp-Nummer der Plattform und bekommt eine Kurzanleitung. Danach kommen Aufträge direkt aufs Handy.
            Mit „Aufträge“ sieht er jederzeit seine offenen Aufträge.
        </div>
    </div>
</div>
<script <?= Html::nonce() ?>>
$('.bauhof-ma-loeschen').on('submit', function (e) {
    if (!confirm('Mitarbeiter wirklich löschen? Seine Nummer wird entfernt; Fotos und Nachrichten bleiben bei den Meldungen.')) e.preventDefault();
});
</script>
