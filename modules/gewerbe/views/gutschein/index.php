<?php
/**
 * @var \humhub\modules\gewerbe\models\Gutschein[] $gutscheine
 * @var \humhub\modules\gewerbe\models\Betrieb[] $mine bestätigte Verwaltungen der Person
 */

use humhub\helpers\Html;

$space = $this->context->contentContainer;
?>
<?= $this->render('_stil') ?>
<div class="panel panel-default">
    <div class="panel-heading"><strong><?= Yii::t('GewerbeModule.base', 'Gutscheine aus Uffenheim') ?></strong></div>
    <div class="panel-body">
        <p class="help-block"><?= Yii::t('GewerbeModule.base', 'Rabatte der Betriebe aus Uffenheim und den Ortsteilen.') ?>
            <?= Yii::t('GewerbeModule.base', 'So geht\'s: Gutschein antippen, im Laden an der Kasse den QR-Code auf dem Aufsteller mit der Handykamera scannen und die Bestätigung vorzeigen.') ?>
            <?= Yii::t('GewerbeModule.base', 'Kostenlos, du brauchst nur ein Konto.') ?></p>
        <?php if ($gutscheine): ?>
            <div class="gs-liste">
                <?php foreach ($gutscheine as $g): ?>
                    <?= $this->render('_ticket', ['g' => $g]) ?>
                <?php endforeach ?>
            </div>
        <?php else: ?>
            <p><?= Yii::t('GewerbeModule.base', 'Gerade gibt es keine Gutscheine. Schau bald wieder vorbei!') ?></p>
        <?php endif ?>
    </div>
</div>
<div class="panel panel-default">
    <div class="panel-body">
        <?php if ($mine): ?>
            <p><strong><?= Yii::t('GewerbeModule.base', 'Gutschein für deinen Betrieb anlegen') ?></strong></p>
            <div style="display:flex;flex-wrap:wrap;gap:6px">
                <?php foreach ($mine as $b): ?>
                    <?= Html::a('<i class="fa fa-ticket" aria-hidden="true"></i> ' . Html::encode($b->name),
                        $space->createUrl('/gewerbe/gutschein/verwaltung', ['betrieb' => $b->id]), ['class' => 'btn btn-light btn-sm']) ?>
                <?php endforeach ?>
            </div>
        <?php else: ?>
            <p class="text-muted" style="margin:0"><?= Yii::t('GewerbeModule.base', 'Du hast einen Betrieb in Uffenheim? Übernimm seine Seite im {link}, dann kannst du selbst Gutscheine anlegen – kostenlos.',
                ['link' => Html::a(Yii::t('GewerbeModule.base', 'Gewerbeverzeichnis'), $space->createUrl('/gewerbe/betrieb/index'))]) ?></p>
        <?php endif ?>
    </div>
</div>
