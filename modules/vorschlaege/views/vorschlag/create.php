<?php
/**
 * @var \humhub\modules\vorschlaege\models\VorschlagForm $form
 * @var \humhub\modules\space\models\Space $space
 * @var \humhub\modules\vorschlaege\Module $module
 */

use humhub\helpers\Html;
use humhub\widgets\form\ActiveForm;

$kategorien = [];
foreach ($module->kategorien as $k) {
    $kategorien[$k] = $module->kategorieLabel($k);
}
$zurueck = $space->createUrl('/vorschlaege/vorschlag/index');
?>
<div class="panel panel-default">
    <div class="panel-body">
        <p><?= Html::a('<i class="fa fa-arrow-left"></i> ' . Yii::t('VorschlaegeModule.base', 'Alle Vorschläge'), $zurueck) ?></p>
        <h1 style="font-size:1.4em;margin-top:0"><?= Yii::t('VorschlaegeModule.base', 'Vorschlag einreichen') ?></h1>
        <p class="help-block"><?= Yii::t('VorschlaegeModule.base', 'Eine Idee je Vorschlag, bitte. Schau vorher kurz in die {liste} – gibt es deine Idee schon, stimm lieber dort dafür und ergänze sie in den Kommentaren.',
            ['liste' => Html::a(Yii::t('VorschlaegeModule.base', 'Liste'), $zurueck)]) ?></p>
        <?php $f = ActiveForm::begin(['action' => $space->createUrl('/vorschlaege/vorschlag/create'), 'enableClientValidation' => false]) ?>
            <?= $f->field($form, 'titel')->textInput(['maxlength' => 150, 'placeholder' => Yii::t('VorschlaegeModule.base', 'z. B. „Abfuhrtermine als Kalender-Abo“')]) ?>
            <?= $f->field($form, 'kategorie')->dropDownList($kategorien) ?>
            <?= $f->field($form, 'text')->textarea(['rows' => 7, 'maxlength' => 5000,
                'placeholder' => Yii::t('VorschlaegeModule.base', 'Was soll anders werden, und warum? Was würde es dir oder anderen bringen?')]) ?>
            <p class="help-block"><?= Yii::t('VorschlaegeModule.base', 'Dein Vorschlag erscheint mit deinem Namen für alle sichtbar, auch ohne Anmeldung.') ?></p>
            <button type="submit" class="btn btn-primary"><?= Yii::t('VorschlaegeModule.base', 'Vorschlag einreichen') ?></button>
            <?= Html::a(Yii::t('VorschlaegeModule.base', 'Abbrechen'), $zurueck, ['class' => 'btn btn-link']) ?>
        <?php ActiveForm::end() ?>
    </div>
</div>
