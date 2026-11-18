<?php
/**
 * @var bool $aktiv
 * @var bool $mail
 * @var string $email
 * @var \humhub\modules\wochenblick\Module $module
 */

use humhub\helpers\Html;

?>
<div class="panel-heading"><strong><?= Yii::t('WochenblickModule.base', 'Wochenüberblick') ?></strong></div>
<div class="panel-body">
    <p><?= Yii::t('WochenblickModule.base', 'Jeden Freitag gegen 17 Uhr bekommst du einen Überblick über das Wochenende und die kommende Woche: Veranstaltungen, Müllabfuhr, Spiele der Vereine, das Wetter am Wochenende, ein neues Mitteilungsblatt und neue Gutscheine. Als Push-Nachricht aufs Handy (wenn du es auf deinem Gerät erlaubt hast) und auf Wunsch zusätzlich als E-Mail.') ?></p>
    <p class="help-block"><?= Yii::t('WochenblickModule.base', 'Für die Müllabfuhr gilt dein Gebiet aus der {link} (sonst Kernstadt).', ['link' => Html::a(Yii::t('WochenblickModule.base', 'Abfuhr-Erinnerung'), ['/abfuhr/einstellungen'])]) ?></p>

    <?= Html::beginForm(['index'], 'post') ?>
    <div class="checkbox" style="margin-bottom:10px">
        <label style="font-weight:600"><?= Html::checkbox('aktiv', $aktiv) ?> <?= Yii::t('WochenblickModule.base', 'Wochenüberblick einschalten') ?></label>
    </div>
    <div class="checkbox" style="margin-bottom:14px">
        <label><?= Html::checkbox('mail', $mail) ?> <?= Yii::t('WochenblickModule.base', 'Auch als E-Mail an {adresse}', ['adresse' => Html::encode($email)]) ?></label>
    </div>
    <button type="submit" class="btn btn-primary"><?= Yii::t('WochenblickModule.base', 'Speichern') ?></button>
    <?= Html::endForm() ?>

    <hr>
    <p><?= Html::a('<i class="fa fa-eye"></i> ' . Yii::t('WochenblickModule.base', 'Überblick dieser Woche ansehen'), ['/wochenblick/ansicht']) ?></p>
    <?php if ($aktiv): ?>
        <?= Html::beginForm(['test'], 'post', ['style' => 'margin-top:8px']) ?>
        <button type="submit" class="btn btn-light btn-sm"><i class="fa fa-paper-plane-o"></i> <?= Yii::t('WochenblickModule.base', 'Jetzt zur Probe schicken') ?></button>
        <?= Html::endForm() ?>
    <?php endif ?>
</div>
