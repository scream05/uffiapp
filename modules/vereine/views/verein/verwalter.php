<?php
/**
 * @var \humhub\modules\vereine\models\Verein $verein
 * @var \humhub\modules\vereine\models\VereinVerwalter[] $rows
 */

use humhub\helpers\Html;
use humhub\modules\user\widgets\Image;
use humhub\modules\vereine\models\VereinVerwalter;

$space = $this->context->contentContainer;
$button = fn($row, $aktion, $label, $class, $confirm = null) => Html::beginForm($space->createUrl('/vereine/verein/verwalter', ['id' => $verein->id]), 'post', ['style' => 'display:inline'])
    . Html::hiddenInput('user_id', $row->user_id) . Html::hiddenInput('aktion', $aktion)
    . Html::submitButton($label, ['class' => "btn btn-sm $class"] + ($confirm ? ['data-confirm' => $confirm] : []))
    . Html::endForm();
?>
<div class="panel panel-default">
    <div class="panel-heading"><strong>Verwalter: <?= Html::encode($verein->name) ?></strong></div>
    <div class="panel-body">
        <p><?= Html::a('<i class="fa fa-arrow-left"></i> Zur Vereinsseite', $verein->getUrl()) ?></p>
        <p class="help-block">Verwalter pflegen die Vorstellungsseite und tragen Termine in den gemeinsamen Kalender ein.
            Bestätigen Sie nur Personen, die tatsächlich für den Verein sprechen dürfen.</p>
        <?php if (!$rows): ?>
            <p class="text-muted">Noch keine Verwalter.</p>
        <?php endif ?>
        <?php foreach ($rows as $row): ?>
            <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;padding:8px 0;border-bottom:1px solid rgba(0,0,0,.06)">
                <?= Image::widget(['user' => $row->user, 'width' => 32]) ?>
                <div style="flex:1;min-width:160px">
                    <?= Html::a(Html::encode($row->user->displayName), $row->user->getUrl()) ?>
                    <?php if ($row->status === VereinVerwalter::STATUS_ANGEFRAGT): ?>
                        <span class="label label-warning">angefragt</span>
                    <?php endif ?>
                    <?php if ($row->nachricht): ?>
                        <br><small class="text-muted">„<?= Html::encode($row->nachricht) ?>“</small>
                    <?php endif ?>
                </div>
                <?php if ($row->status === VereinVerwalter::STATUS_ANGEFRAGT): ?>
                    <?= $button($row, 'bestaetigen', 'Bestätigen', 'btn-primary') ?>
                    <?= $button($row, 'ablehnen', 'Ablehnen', 'btn-light') ?>
                <?php else: ?>
                    <?= $button($row, 'entfernen', $row->user_id === Yii::$app->user->id ? 'Mich entfernen' : 'Entfernen', 'btn-light',
                        'Wirklich als Verwalter entfernen?') ?>
                <?php endif ?>
            </div>
        <?php endforeach ?>
        <p class="help-block" style="margin-top:12px">Weitere Vorstandsmitglieder fragen die Verwaltung auf der Vereinsseite an; Sie können sie dann hier bestätigen.</p>
    </div>
</div>
