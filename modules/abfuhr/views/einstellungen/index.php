<?php
/**
 * @var bool $aktiv
 * @var string $gebiet
 * @var string[] $tonnen
 * @var string $zeit
 * @var array $termine Y-m-d => Tonnen-Schlüssel
 * @var \humhub\modules\abfuhr\Module $module
 */

use humhub\helpers\Html;
use humhub\modules\abfuhr\components\Erinnerung;
use yii\helpers\Url;

?>
<div class="panel-heading"><strong><?= Yii::t('AbfuhrModule.base', 'Abfuhr-Erinnerung') ?></strong></div>
<div class="panel-body">
    <p><?= Yii::t('AbfuhrModule.base', 'Wir erinnern dich rechtzeitig an die Müllabfuhr – als Benachrichtigung hier in der Glocke und, wenn du es auf deinem Gerät erlaubt hast, als Push-Nachricht aufs Handy. Eine E-Mail kannst du zusätzlich unter {link} („Abfuhr-Erinnerung“) einschalten.', [
        'link' => Html::a(Yii::t('AbfuhrModule.base', 'Benachrichtigungen'), ['/notification/user'])]) ?></p>

    <?= Html::beginForm(['index'], 'post') ?>
    <div class="checkbox" style="margin-bottom:14px">
        <label style="font-weight:600"><?= Html::checkbox('aktiv', $aktiv) ?> <?= Yii::t('AbfuhrModule.base', 'Erinnerung einschalten') ?></label>
    </div>

    <div class="mb-3">
        <label class="control-label"><?= Yii::t('AbfuhrModule.base', 'Wo wohnst du?') ?></label>
        <div class="help-block" style="margin-top:0"><?= Yii::t('AbfuhrModule.base', 'Restmüll, Bio- und Papiertonne sind in ganz Uffenheim gleich, nur die Gelbe Tonne kommt in den Ortsteilen an einem anderen Tag.') ?></div>
        <div class="radio"><label><?= Html::radio('gebiet', $gebiet === 'kernstadt', ['value' => 'kernstadt']) ?> <?= Yii::t('AbfuhrModule.base', 'Kernstadt Uffenheim') ?></label></div>
        <div class="radio"><label><?= Html::radio('gebiet', $gebiet === 'ortsteil', ['value' => 'ortsteil']) ?>
                <?= Yii::t('AbfuhrModule.base', 'Ortsteil (Brackenlohr, Custenlohr mit Vorder-/Hinterpfeinach, Langensteinach, Rudolzhofen, Uttenhofen, Wallmersbach, Welbhausen)') ?></label></div>
    </div>

    <div class="mb-3">
        <label class="control-label"><?= Yii::t('AbfuhrModule.base', 'Woran erinnern?') ?></label>
        <?php foreach (array_keys($module->tonnen) as $key): ?>
            <div class="checkbox"><label><?= Html::checkbox('tonnen[]', in_array($key, $tonnen, true), ['value' => $key]) ?> <?= Html::encode($module->tonneLabel($key)) ?></label></div>
        <?php endforeach ?>
    </div>

    <div class="mb-3">
        <label class="control-label"><?= Yii::t('AbfuhrModule.base', 'Wann?') ?></label>
        <?php foreach (array_keys($module->zeiten) as $key): ?>
            <div class="radio"><label><?= Html::radio('zeit', $zeit === (string)$key, ['value' => $key]) ?> <?= Html::encode($module->zeitLabel((string)$key)) ?></label></div>
        <?php endforeach ?>
    </div>

    <button type="submit" class="btn btn-primary"><?= Yii::t('AbfuhrModule.base', 'Speichern') ?></button>
    <?= Html::endForm() ?>

    <hr>
    <strong><?= Yii::t('AbfuhrModule.base', 'Deine nächsten Abfuhren') ?></strong> <small class="text-muted">(<?= $gebiet === 'ortsteil' ? Yii::t('AbfuhrModule.base', 'Ortsteile, nächste 4 Wochen') : Yii::t('AbfuhrModule.base', 'Kernstadt, nächste 4 Wochen') ?>)</small>
    <?php if (!$termine): ?>
        <p class="text-muted"><?= Yii::t('AbfuhrModule.base', 'Keine Termine gefunden.') ?></p>
    <?php else: ?>
        <ul class="list-unstyled" style="margin-top:6px">
            <?php foreach ($termine as $tag => $keys): ?>
                <?php $keys = array_values(array_intersect($keys, $tonnen ?: array_keys($module->tonnen))); if (!$keys) continue; ?>
                <li style="padding:3px 0"><strong><?= Html::encode(Erinnerung::tag($tag, true)) ?></strong> – <?= Html::encode(Erinnerung::liste($keys, true)) ?></li>
            <?php endforeach ?>
        </ul>
    <?php endif ?>
    <p class="help-block"><?= Yii::t('AbfuhrModule.base', 'Quelle: Abfallkalender des Landkreises Neustadt a.d.Aisch-Bad Windsheim; Sonderfälle (z. B. Feiertagsverschiebungen) im {link} prüfen.', [
        'link' => Html::a(Yii::t('AbfuhrModule.base', 'Abfallkalender des Landkreises'), 'https://www.kreis-nea.de/lebenslagen/abfallwirtschaft/abfalltermine', ['target' => '_blank', 'rel' => 'noopener'])]) ?></p>

    <?php if ($aktiv): ?>
        <?= Html::beginForm(['test'], 'post', ['style' => 'margin-top:8px']) ?>
        <button type="submit" class="btn btn-light btn-sm"><i class="fa fa-bell-o"></i> <?= Yii::t('AbfuhrModule.base', 'Test-Erinnerung jetzt senden') ?></button>
        <?= Html::endForm() ?>
    <?php endif ?>
</div>
