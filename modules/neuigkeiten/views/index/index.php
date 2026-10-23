<?php
/**
 * @var \humhub\modules\neuigkeiten\Module $module
 * @var array[] $eintraege
 * @var string $art
 */

use humhub\helpers\Html;
use humhub\modules\neuigkeiten\assets\NeuigkeitenAsset;
use humhub\modules\neuigkeiten\Module;

NeuigkeitenAsset::register($this);
$monat = null;
?>
<div class="container">
<div class="panel panel-default" style="max-width:760px;margin:0 auto">
    <div class="panel-body">
        <h1 style="font-size:1.4em;margin-top:0"><?= Yii::t('NeuigkeitenModule.base', 'Neuigkeiten') ?></h1>
        <p class="help-block"><?= Yii::t('NeuigkeitenModule.base', 'Was ist neu auf der Plattform, was haben wir verbessert oder behoben? Hier steht es, neueste zuerst. Wünsche und Ideen nehmen wir gern unter {ideen} entgegen.',
            ['ideen' => ($s = \humhub\modules\space\models\Space::findOne(['url' => 'vorschlaege']))
                ? Html::a(Yii::t('NeuigkeitenModule.base', 'Ideen & Vorschläge'), $s->createUrl('/vorschlaege/vorschlag/index'))
                : Yii::t('NeuigkeitenModule.base', 'Ideen & Vorschläge')]) ?>
            <?php if (!Yii::$app->user->isGuest): ?>
                <?= Html::a(Yii::t('NeuigkeitenModule.base', 'Willkommensrundgang noch einmal ansehen'), ['/dashboard/dashboard', 'rundgang' => 1], ['data-pjax-prevent' => 1]) ?>
            <?php endif ?></p>
        <nav class="nk-filter" aria-label="<?= Yii::t('NeuigkeitenModule.base', 'Nach Art filtern') ?>">
            <?= Html::a(Yii::t('NeuigkeitenModule.base', 'Alle'), ['/neuigkeiten/index'], ['class' => $art === '' ? 'aktiv' : null, 'aria-current' => $art === '' ? 'page' : null]) ?>
            <?php foreach (array_keys(Module::ARTEN) as $key): ?>
                <?= Html::a(Html::encode(Module::artLabel($key)), ['/neuigkeiten/index', 'art' => $key], ['class' => $art === $key ? 'aktiv' : null, 'aria-current' => $art === $key ? 'page' : null]) ?>
            <?php endforeach ?>
        </nav>
        <?php if (!$eintraege): ?>
            <p><?= Yii::t('NeuigkeitenModule.base', 'Keine Einträge.') ?></p>
        <?php endif ?>
        <?php foreach ($eintraege as $i => $e): ?>
            <?php $m = substr($e['datum'], 0, 7) ?>
            <?php if ($m !== $monat): ?>
                <?= $monat !== null ? '</ul>' : '' ?>
                <h2 class="nk-monat"><?= Html::encode(Yii::$app->formatter->asDate($e['datum'], 'LLLL yyyy')) ?></h2>
                <ul class="nk-liste">
                <?php $monat = $m ?>
            <?php endif ?>
            <li class="nk-eintrag" id="<?= Html::encode($e['id']) ?>">
                <div class="nk-datum"><?= Yii::$app->formatter->asDate($e['datum'], 'medium') ?></div>
                <div>
                    <span class="nk-art nk-art-<?= Html::encode($e['art']) ?>"><i class="fa fa-<?= Module::ARTEN[$e['art']][1] ?? 'circle' ?>" aria-hidden="true"></i><?= Html::encode(Module::artLabel($e['art'])) ?></span>
                    <h3><?= Html::encode($module->feld($e, 'titel')) ?></h3>
                    <p><?= Html::encode($module->feld($e, 'text')) ?>
                        <?php if (($url = $module->linkUrl($e)) !== null && $e['id'] !== 'neuigkeiten'): ?>
                            <?= Html::a(Yii::t('NeuigkeitenModule.base', 'Ansehen') . ' <i class="fa fa-arrow-right" aria-hidden="true"></i>', $url, ['class' => 'nk-ansehen']) ?>
                        <?php endif ?>
                    </p>
                </div>
            </li>
        <?php endforeach ?>
        <?= $monat !== null ? '</ul>' : '' ?>
    </div>
</div>
</div>
