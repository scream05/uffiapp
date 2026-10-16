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
    <div class="panel-heading"><strong>Gutscheine aus Uffenheim</strong></div>
    <div class="panel-body">
        <p class="help-block">Rabatte der Betriebe aus Uffenheim und den Ortsteilen. So geht's: Gutschein antippen, im Laden an der Kasse
            den QR-Code auf dem Aufsteller mit der Handykamera scannen und die Bestätigung vorzeigen. Kostenlos, du brauchst nur ein Konto.</p>
        <?php if ($gutscheine): ?>
            <div class="gs-liste">
                <?php foreach ($gutscheine as $g): ?>
                    <?= $this->render('_ticket', ['g' => $g]) ?>
                <?php endforeach ?>
            </div>
        <?php else: ?>
            <p>Gerade gibt es keine Gutscheine. Schau bald wieder vorbei!</p>
        <?php endif ?>
    </div>
</div>
<div class="panel panel-default">
    <div class="panel-body">
        <?php if ($mine): ?>
            <p><strong>Gutschein für deinen Betrieb anlegen</strong></p>
            <div style="display:flex;flex-wrap:wrap;gap:6px">
                <?php foreach ($mine as $b): ?>
                    <?= Html::a('<i class="fa fa-ticket" aria-hidden="true"></i> ' . Html::encode($b->name),
                        $space->createUrl('/gewerbe/gutschein/verwaltung', ['betrieb' => $b->id]), ['class' => 'btn btn-light btn-sm']) ?>
                <?php endforeach ?>
            </div>
        <?php else: ?>
            <p class="text-muted" style="margin:0">Du hast einen Betrieb in Uffenheim? Übernimm seine Seite im
                <?= Html::a('Gewerbeverzeichnis', $space->createUrl('/gewerbe/betrieb/index')) ?>, dann kannst du selbst Gutscheine anlegen – kostenlos.</p>
        <?php endif ?>
    </div>
</div>
