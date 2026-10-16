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
        <p><?= Html::a('<i class="fa fa-arrow-left" aria-hidden="true"></i> Gutscheine von ' . Html::encode($betrieb->name), $zurueck) ?></p>
        <h1 style="font-size:1.4em;margin-top:0"><?= $isNew ? 'Neuer Gutschein' : 'Gutschein bearbeiten' ?></h1>
        <p class="help-block">Ein Rabatt für die Kundschaft in Uffenheim. Der Gutschein ist sofort für alle sichtbar. Eingelöst wird bei dir im Laden:
            Die Kundin oder der Kunde scannt den Theken-QR an deiner Kasse und zeigt dir die Bestätigung mit Uhrzeit und Code.</p>
        <?php $form = ActiveForm::begin() ?>
            <?= $form->field($g, 'titel')->textInput(['maxlength' => 120, 'placeholder' => 'z. B. 10 % auf alles, Gratis-Kaffee zum Kuchen, 50 € auf die Grundgebühr']) ?>
            <?= $form->field($g, 'bedingungen')->textarea(['rows' => 3, 'maxlength' => 1000,
                'placeholder' => 'z. B. ab 20 € Einkauf, nicht mit anderen Aktionen kombinierbar, nicht für reduzierte Ware']) ?>
            <div style="display:flex;flex-wrap:wrap;gap:12px">
                <div style="flex:1 1 180px"><?= $form->field($g, 'gueltig_ab')->input('date') ?></div>
                <div style="flex:1 1 180px"><?= $form->field($g, 'gueltig_bis')->input('date') ?></div>
            </div>
            <div style="display:flex;flex-wrap:wrap;gap:12px">
                <div style="flex:1 1 180px"><?= $form->field($g, 'pro_person')->dropDownList(Gutschein::PRO_PERSON) ?></div>
                <div style="flex:1 1 180px"><?= $form->field($g, 'limit_gesamt')->input('number', ['min' => 1, 'placeholder' => 'ohne Begrenzung'])
                        ->hint('z. B. „die ersten 50“') ?></div>
            </div>
            <?php if (Oeffnungszeiten::hatZeiten($betrieb->getZeiten())): ?>
                <?= $form->field($g, 'nur_geoeffnet')->checkbox() ?>
            <?php endif ?>
            <?php if ($isNew): ?>
                <div class="alert alert-info">
                    <strong>Bitte beachten</strong>
                    <ul style="margin:6px 0 0;padding-left:1.2em">
                        <li>Nur Rabatte und Zugaben deines eigenen Betriebs, keine Gutscheine mit Geldwert zum Verkaufen oder Aufladen.</li>
                        <li>Keine Rabatte auf rezeptpflichtige Arzneimittel und Tabakwaren; Rabatte auf Alkohol nur, wenn du an der Kasse das Alter prüfst.</li>
                        <li>Bedingungen klar nennen. Was du zusagst, musst du einhalten, solange der Gutschein läuft.</li>
                        <li>Du kannst den Gutschein jederzeit pausieren oder beenden. Wer eingelöst hat, siehst du nicht – nur Uhrzeit und Code.</li>
                    </ul>
                </div>
                <?= $form->field($g, 'regelnOk')->checkbox() ?>
            <?php endif ?>
            <button type="submit" class="btn btn-primary"><?= $isNew ? 'Veröffentlichen' : 'Speichern' ?></button>
            <?= Html::a('Abbrechen', $zurueck, ['class' => 'btn btn-link']) ?>
        <?php ActiveForm::end() ?>
    </div>
</div>
