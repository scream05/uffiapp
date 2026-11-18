<?php
/**
 * @var \humhub\modules\kurse\models\Kurs[] $kurse
 * @var array $verwaltung [['verein' => Verein, 'kurse' => Kurs[]], …]
 * @var bool $oeffentlich
 */

use humhub\helpers\Html;

$space = $this->context->contentContainer;
echo $this->render('_stil');
?>
<?php if ($verwaltung): ?>
    <div class="panel panel-default">
        <div class="panel-heading"><strong><?= Yii::t('KurseModule.base', 'Kurse verwalten') ?></strong></div>
        <div class="panel-body">
            <?php if (!$oeffentlich): ?>
                <div class="alert alert-warning"><?= Yii::t('KurseModule.base', 'Testphase: Die Kursseiten sehen bisher nur Admins und Kursverwalter. Die Links aus den Mails funktionieren für alle.') ?></div>
            <?php endif ?>
            <?php foreach ($verwaltung as $v): ?>
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-2 mb-2">
                    <h4 class="m-0"><?= Html::encode($v['verein']->name) ?></h4>
                    <?= Html::a('<i class="fa fa-plus"></i> ' . Html::encode(Yii::t('KurseModule.base', 'Neuer Kurs')), $space->createUrl('/kurse/kurs/create', ['verein' => $v['verein']->id]), ['class' => 'btn btn-primary btn-sm']) ?>
                </div>
                <?php $seite = \humhub\modules\kurse\models\KursVerein::fuer($v['verein']->id); ?>
                <?php if ($seite && $seite->hatSeite()): ?>
                    <p class="small"><i class="fa fa-globe"></i> <?= Yii::t('KurseModule.base', 'Eigene Kursseite zum Verlinken:') ?>
                        <?= Html::a(Html::encode($seite->seiteUrl('', [], true)), $seite->seiteUrl(), ['target' => '_blank']) ?>
                        <?= $seite->seite_aktiv ? '' : '<span class="badge text-bg-warning">' . Html::encode(Yii::t('KurseModule.base', 'Vorschau – noch nicht für alle sichtbar')) . '</span>' ?></p>
                <?php endif ?>
                <?php if (!$v['kurse']): ?>
                    <p class="text-muted"><?= Yii::t('KurseModule.base', 'Noch keine Kurse angelegt.') ?></p>
                <?php endif ?>
                <?php foreach ($v['kurse'] as $kurs): ?>
                    <?= $this->render('_karte', ['kurs' => $kurs, 'mitVerein' => false]) ?>
                <?php endforeach ?>
            <?php endforeach ?>
        </div>
    </div>
<?php endif ?>

<div class="panel panel-default">
    <div class="panel-heading"><strong><?= Yii::t('KurseModule.base', 'Kurse der Vereine') ?></strong></div>
    <div class="panel-body">
        <p class="help-block"><?= Yii::t('KurseModule.base', 'Schwimmkurse und andere Angebote der Uffenheimer Vereine. Die Anmeldung geht ohne Konto – du bekommst eine Mail zum Bestätigen.') ?></p>
        <?php if (!$kurse): ?>
            <p class="text-muted"><?= Yii::t('KurseModule.base', 'Gerade sind keine Kurse ausgeschrieben.') ?></p>
        <?php endif ?>
        <?php foreach ($kurse as $kurs): ?>
            <?= $this->render('_karte', ['kurs' => $kurs, 'mitVerein' => true]) ?>
        <?php endforeach ?>
    </div>
</div>
