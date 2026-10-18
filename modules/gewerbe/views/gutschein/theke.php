<?php
/**
 * Ziel des Theken-QR im Laden
 * @var \humhub\modules\gewerbe\models\Betrieb|null $betrieb null = QR ungültig (z. B. erneuert)
 * @var \humhub\modules\gewerbe\models\Gutschein[] $gutscheine
 * @var string $t Token aus dem QR
 */

use humhub\helpers\Html;
use humhub\modules\gewerbe\components\GutscheinDienst;

$space = $this->context->contentContainer;
?>
<?= $this->render('_stil') ?>
<div class="panel panel-default">
    <div class="panel-body">
        <?php if ($betrieb === null): ?>
            <h1 style="font-size:1.3em;margin-top:0"><?= Yii::t('GewerbeModule.base', 'QR-Code nicht mehr gültig') ?></h1>
            <p><?= Yii::t('GewerbeModule.base', 'Dieser Aufsteller gilt nicht mehr. Bitte frag an der Kasse nach dem aktuellen QR-Code.') ?></p>
            <?= Html::a(Yii::t('GewerbeModule.base', 'Alle Gutscheine'), $space->createUrl('/gewerbe/gutschein/index'), ['class' => 'btn btn-light']) ?>
        <?php else: ?>
            <div style="display:flex;gap:14px;align-items:center;margin-bottom:14px">
                <?= $this->render('@gewerbe/views/betrieb/_logo', ['betrieb' => $betrieb, 'size' => 56]) ?>
                <div style="min-width:0">
                    <div class="text-muted"><?= Yii::t('GewerbeModule.base', 'Du bist bei') ?></div>
                    <h1 style="font-size:1.3em;margin:0"><?= Html::encode($betrieb->name) ?></h1>
                </div>
            </div>
            <?php if (!$gutscheine): ?>
                <p><?= Yii::t('GewerbeModule.base', 'Hier gibt es gerade keinen Gutschein.') ?></p>
            <?php endif ?>
            <?php foreach ($gutscheine as $g): $grund = GutscheinDienst::grund($g, Yii::$app->user->id); ?>
                <div class="gs-ticket<?= $grund === null ? '' : ' zu' ?>" style="margin-bottom:12px;flex-direction:column;align-items:stretch">
                    <span class="gs-titel"><?= Html::encode($g->titel) ?></span>
                    <?php if ($g->bedingungen): ?>
                        <small style="white-space:pre-line"><?= Html::encode($g->bedingungen) ?></small>
                    <?php endif ?>
                    <?php if ($grund === null): ?>
                        <?= Html::beginForm($space->createUrl('/gewerbe/gutschein/einloesen', ['id' => $g->id]), 'post', ['style' => 'margin-top:10px']) ?>
                            <?= Html::hiddenInput('t', $t) ?>
                            <button type="submit" class="btn btn-primary btn-lg" style="width:100%"
                                    data-confirm="<?= Html::encode(Yii::t('GewerbeModule.base', 'Jetzt einlösen? Zeig danach den Bildschirm dem Personal. Der Gutschein ist dann verbraucht.')) ?>">
                                <i class="fa fa-check" aria-hidden="true"></i> <?= Yii::t('GewerbeModule.base', 'Jetzt einlösen') ?></button>
                        <?= Html::endForm() ?>
                    <?php else: ?>
                        <span class="gs-stand"><?= Html::encode($grund) ?></span>
                    <?php endif ?>
                </div>
            <?php endforeach ?>
            <p class="help-block"><?= Yii::t('GewerbeModule.base', 'Tipp erst an der Kasse auf „Jetzt einlösen“ – die Bestätigung gilt {minuten} Minuten.', ['minuten' => (int)Yii::$app->getModule('gewerbe')->bestaetigungMinuten]) ?></p>
        <?php endif ?>
    </div>
</div>
