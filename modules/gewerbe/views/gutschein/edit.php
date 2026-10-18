<?php
/**
 * @var \humhub\modules\gewerbe\models\Gutschein $g
 * @var \humhub\modules\gewerbe\models\Betrieb $betrieb
 * @var bool $isNew
 */

use humhub\helpers\Html;
use humhub\modules\gewerbe\components\Oeffnungszeiten;
use humhub\modules\gewerbe\models\Gutschein;
use humhub\widgets\form\ActiveForm;

$space = $this->context->contentContainer;
$zurueck = $space->createUrl('/gewerbe/gutschein/verwaltung', ['betrieb' => $betrieb->id]);
?>
<div class="panel panel-default">
    <div class="panel-body">
        <p><?= Html::a('<i class="fa fa-arrow-left" aria-hidden="true"></i> ' . Yii::t('GewerbeModule.base', 'Gutscheine von {name}', ['name' => Html::encode($betrieb->name)]), $zurueck) ?></p>
        <h1 style="font-size:1.4em;margin-top:0"><?= $isNew ? Yii::t('GewerbeModule.base', 'Neuer Gutschein') : Yii::t('GewerbeModule.base', 'Gutschein bearbeiten') ?></h1>
        <p class="help-block"><?= Yii::t('GewerbeModule.base', 'Ein Rabatt für die Kundschaft in Uffenheim. Der Gutschein ist sofort für alle sichtbar.') ?>
            <?= Yii::t('GewerbeModule.base', 'Eingelöst wird bei dir im Laden: Die Kundin oder der Kunde scannt den Theken-QR an deiner Kasse und zeigt dir die Bestätigung mit Uhrzeit und Code.') ?></p>
        <?php $form = ActiveForm::begin() ?>
            <?= $form->field($g, 'titel')->textInput(['maxlength' => 120, 'placeholder' => Yii::t('GewerbeModule.base', 'z. B. 10 % auf alles, Gratis-Kaffee zum Kuchen, 50 € auf die Grundgebühr')]) ?>
            <?= $form->field($g, 'bedingungen')->textarea(['rows' => 3, 'maxlength' => 1000,
                'placeholder' => Yii::t('GewerbeModule.base', 'z. B. ab 20 € Einkauf, nicht mit anderen Aktionen kombinierbar, nicht für reduzierte Ware')]) ?>
            <div style="display:flex;flex-wrap:wrap;gap:12px">
                <div style="flex:1 1 180px"><?= $form->field($g, 'gueltig_ab')->input('date') ?></div>
                <div style="flex:1 1 180px"><?= $form->field($g, 'gueltig_bis')->input('date') ?></div>
            </div>
            <div style="display:flex;flex-wrap:wrap;gap:12px">
                <div style="flex:1 1 180px"><?= $form->field($g, 'pro_person')->dropDownList(Gutschein::proPersonLabels()) ?></div>
                <div style="flex:1 1 180px"><?= $form->field($g, 'limit_gesamt')->input('number', ['min' => 1, 'placeholder' => Yii::t('GewerbeModule.base', 'ohne Begrenzung')])
                        ->hint(Yii::t('GewerbeModule.base', 'z. B. „die ersten 50“')) ?></div>
            </div>
            <?php if (Oeffnungszeiten::hatZeiten($betrieb->getZeiten())): ?>
                <?= $form->field($g, 'nur_geoeffnet')->checkbox() ?>
            <?php endif ?>
            <?php if ($isNew): ?>
                <div class="alert alert-info">
                    <strong><?= Yii::t('GewerbeModule.base', 'Bitte beachten') ?></strong>
                    <ul style="margin:6px 0 0;padding-left:1.2em">
                        <li><?= Yii::t('GewerbeModule.base', 'Nur Rabatte und Zugaben deines eigenen Betriebs, keine Gutscheine mit Geldwert zum Verkaufen oder Aufladen.') ?></li>
                        <li><?= Yii::t('GewerbeModule.base', 'Keine Rabatte auf rezeptpflichtige Arzneimittel und Tabakwaren; Rabatte auf Alkohol nur, wenn du an der Kasse das Alter prüfst.') ?></li>
                        <li><?= Yii::t('GewerbeModule.base', 'Bedingungen klar nennen. Was du zusagst, musst du einhalten, solange der Gutschein läuft.') ?></li>
                        <li><?= Yii::t('GewerbeModule.base', 'Du kannst den Gutschein jederzeit pausieren oder beenden. Wer eingelöst hat, siehst du nicht – nur Uhrzeit und Code.') ?></li>
                    </ul>
                </div>
                <?= $form->field($g, 'regelnOk')->checkbox() ?>
            <?php endif ?>
            <button type="submit" class="btn btn-primary"><?= $isNew ? Yii::t('GewerbeModule.base', 'Veröffentlichen') : Yii::t('GewerbeModule.base', 'Speichern') ?></button>
            <?= Html::a(Yii::t('GewerbeModule.base', 'Abbrechen'), $zurueck, ['class' => 'btn btn-link']) ?>
        <?php ActiveForm::end() ?>
    </div>
</div>
