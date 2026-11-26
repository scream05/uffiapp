<?php
/**
 * Einstellungen eines Vereins im Kursmodul (für Kursverwalter)
 *
 * @var \humhub\modules\kurse\models\KursVerein $kv
 * @var \humhub\modules\vereine\models\Verein $verein
 */

use humhub\helpers\Html;
use humhub\widgets\form\ActiveForm;

$zurueck = $this->context->contentContainer->createUrl('/kurse/kurs/index');
?>
<div class="panel panel-default">
    <div class="panel-heading"><strong><?= Yii::t('KurseModule.base', 'Kurs-Einstellungen: {verein}', ['verein' => Html::encode($verein->name)]) ?></strong></div>
    <div class="panel-body">
        <?php $f = ActiveForm::begin(['id' => 'kurs-einstellungen']) ?>
        <?= $f->field($kv, 'mitglied_url')->input('url', ['placeholder' => 'https://…', 'maxlength' => 500])
            ->hint(Html::encode(Yii::t('KurseModule.base', 'Erscheint auf der Kursseite, bei jedem Kurs und im Anmeldeformular als „Noch kein Mitglied?“. Ändert sich der Link (z. B. neue PDF-Version), hier einfach den neuen eintragen. Leer = kein Hinweis.'))) ?>
        <?php if ($kv->hatSeite()): ?>
            <?= $f->field($kv, 'impressum_url')->input('url', ['placeholder' => 'https://…', 'maxlength' => 255])
                ->hint(Html::encode(Yii::t('KurseModule.base', 'Für die eigene Kursseite. Leer = Impressum der Plattform.'))) ?>
        <?php endif ?>
        <button type="submit" class="btn btn-primary"><?= Yii::t('KurseModule.base', 'Speichern') ?></button>
        <?= Html::a(Html::encode(Yii::t('KurseModule.base', 'Abbrechen')), $zurueck, ['class' => 'btn btn-link']) ?>
        <?php ActiveForm::end() ?>
    </div>
</div>
