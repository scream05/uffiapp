<?php
/**
 * @var \humhub\modules\gewerbe\models\Betrieb $betrieb
 * @var \humhub\modules\gewerbe\models\BetriebVerwalter[] $rows
 */

use humhub\helpers\Html;
use humhub\modules\user\widgets\Image;
use humhub\modules\gewerbe\models\BetriebVerwalter;

$space = $this->context->contentContainer;
$button = fn($row, $aktion, $label, $class, $confirm = null) => Html::beginForm($space->createUrl('/gewerbe/betrieb/verwalter', ['id' => $betrieb->id]), 'post', ['style' => 'display:inline'])
    . Html::hiddenInput('user_id', $row->user_id) . Html::hiddenInput('aktion', $aktion)
    . Html::submitButton($label, ['class' => "btn btn-sm $class"] + ($confirm ? ['data-confirm' => $confirm] : []))
    . Html::endForm();
?>
<div class="panel panel-default">
    <div class="panel-heading"><strong><?= Yii::t('GewerbeModule.base', 'Verwalter: {name}', ['name' => Html::encode($betrieb->name)]) ?></strong></div>
    <div class="panel-body">
        <p><?= Html::a('<i class="fa fa-arrow-left"></i> ' . Yii::t('GewerbeModule.base', 'Zur Betriebsseite'), $betrieb->getUrl()) ?></p>
        <p class="help-block"><?= Yii::t('GewerbeModule.base', 'Verwalter pflegen die Seite des Betriebs im Gewerbeverzeichnis.') ?>
            <?= Yii::t('GewerbeModule.base', 'Bestätige nur Personen, die tatsächlich für den Betrieb sprechen dürfen.') ?></p>
        <?php if (!$rows): ?>
            <p class="text-muted"><?= Yii::t('GewerbeModule.base', 'Noch keine Verwalter.') ?></p>
        <?php endif ?>
        <?php foreach ($rows as $row): ?>
            <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;padding:8px 0;border-bottom:1px solid rgba(0,0,0,.06)">
                <?= Image::widget(['user' => $row->user, 'width' => 32]) ?>
                <div style="flex:1;min-width:160px">
                    <?= Html::a(Html::encode($row->user->displayName), $row->user->getUrl()) ?>
                    <?php if ($row->status === BetriebVerwalter::STATUS_ANGEFRAGT): ?>
                        <span class="badge text-bg-warning"><?= Yii::t('GewerbeModule.base', 'angefragt') ?></span>
                    <?php endif ?>
                    <?php if ($row->nachricht): ?>
                        <br><small class="text-muted">„<?= Html::encode($row->nachricht) ?>“</small>
                    <?php endif ?>
                </div>
                <?php if ($row->status === BetriebVerwalter::STATUS_ANGEFRAGT): ?>
                    <?= $button($row, 'bestaetigen', Yii::t('GewerbeModule.base', 'Bestätigen'), 'btn-primary') ?>
                    <?= $button($row, 'ablehnen', Yii::t('GewerbeModule.base', 'Ablehnen'), 'btn-light') ?>
                <?php else: ?>
                    <?= $button($row, 'entfernen', $row->user_id === Yii::$app->user->id ? Yii::t('GewerbeModule.base', 'Mich entfernen') : Yii::t('GewerbeModule.base', 'Entfernen'), 'btn-light',
                        Yii::t('GewerbeModule.base', 'Wirklich als Verwalter entfernen?')) ?>
                <?php endif ?>
            </div>
        <?php endforeach ?>
        <p class="help-block" style="margin-top:12px"><?= Yii::t('GewerbeModule.base', 'Weitere Personen aus dem Betrieb fragen auf der Betriebsseite als Verwalter an; du kannst sie dann hier bestätigen.') ?></p>
    </div>
</div>
