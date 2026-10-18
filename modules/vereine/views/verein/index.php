<?php
/**
 * @var \humhub\modules\vereine\models\Verein[] $vereine
 * @var \humhub\modules\vereine\models\Verein[] $mine
 * @var string $q
 * @var string $kategorie
 * @var string $ortsteil
 * @var \humhub\modules\vereine\Module $module
 * @var string[] $verwalter Verein-ID => Name des ersten Verwalters (nur für Admins gefüllt)
 */

use humhub\helpers\Html;

$space = $this->context->contentContainer;
$filtered = trim($q) !== '' || $kategorie !== '' || $ortsteil !== '';
?>
<?php if ($mine): ?>
<div class="panel panel-default">
    <div class="panel-heading"><strong><?= Yii::t('VereineModule.base', 'Meine Vereine') ?></strong></div>
    <div class="panel-body">
        <?php foreach ($mine as $v): ?>
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px">
                <?= $this->render('_logo', ['verein' => $v, 'size' => 32]) ?>
                <span style="flex:1"><?= Html::a(Html::encode($v->name), $v->getUrl()) ?></span>
                <?= Html::a('<i class="fa fa-calendar-plus-o"></i> ' . Yii::t('VereineModule.base', 'Termin eintragen'), $space->createUrl('/vereine/termin/create', ['verein' => $v->id]), ['class' => 'btn btn-sm btn-primary']) ?>
            </div>
        <?php endforeach ?>
    </div>
</div>
<?php endif ?>

<div class="panel panel-default">
    <div class="panel-heading"><strong><?= Yii::t('VereineModule.base', 'Vereinsverzeichnis') ?></strong></div>
    <div class="panel-body">
        <p class="help-block"><?= Yii::t('VereineModule.base', 'Vereine, Gruppen und Initiativen in Uffenheim und den Ortsteilen. Ihre Termine stehen im gemeinsamen Kalender unter „Veranstaltungen“. Du bist im Vorstand eines Vereins? Öffne die Seite deines Vereins und frag dort an, ob du ihn verwalten darfst.') ?></p>
        <?= Html::beginForm($space->createUrl('/vereine/verein/index'), 'get', ['class' => 'verein-filter', 'style' => 'display:flex;flex-wrap:wrap;gap:8px;margin-bottom:12px']) ?>
            <?= Html::hiddenInput('cguid', $space->guid) ?>
            <?= Html::textInput('q', $q, ['class' => 'form-control', 'placeholder' => Yii::t('VereineModule.base', 'Suchen …'), 'style' => 'flex:2 1 180px', 'aria-label' => Yii::t('VereineModule.base', 'Suchen')]) ?>
            <?= Html::dropDownList('kategorie', $kategorie, array_combine($module->categories, array_map([$module, 'kategorieLabel'], $module->categories)),
                ['class' => 'form-control', 'prompt' => Yii::t('VereineModule.base', 'Alle Kategorien'), 'style' => 'flex:1 1 150px', 'aria-label' => Yii::t('VereineModule.base', 'Kategorie')]) ?>
            <?= Html::dropDownList('ortsteil', $ortsteil, array_combine($module->ortsteile, array_map([$module, 'ortLabel'], $module->ortsteile)),
                ['class' => 'form-control', 'prompt' => Yii::t('VereineModule.base', 'Alle Orte'), 'style' => 'flex:1 1 130px', 'aria-label' => Yii::t('VereineModule.base', 'Ort')]) ?>
            <button type="submit" class="btn btn-light"><i class="fa fa-search"></i> <?= Yii::t('VereineModule.base', 'Filtern') ?></button>
            <?php if ($filtered): ?>
                <?= Html::a(Yii::t('VereineModule.base', 'Zurücksetzen'), $space->createUrl('/vereine/verein/index'), ['class' => 'btn btn-link']) ?>
            <?php endif ?>
        <?= Html::endForm() ?>

        <p class="text-muted"><?= Yii::t('VereineModule.base', '{n, plural, =1{# Eintrag} other{# Einträge}}', ['n' => count($vereine)]) ?></p>
        <style <?= Html::nonce() ?>>
            /* HumHub macht unter 992 px jede .list-group waagerecht scrollbar (gedacht fürs Space-Menü) – hier senkrecht lassen */
            .verein-liste.list-group { flex-direction: column; overflow-x: visible; margin-left: 0; gap: 0; }
            .verein-liste .list-group-item { white-space: normal; margin: 0 !important; }
            .verein-liste .list-group-item strong { overflow-wrap: anywhere; }
            .verein-liste .verein-status-zeile { display: none; }
            /* Handy: Status als kleine Zeile unter dem Namen statt als Etikett rechts, damit der Name Platz hat */
            @media (max-width: 575.98px) {
                .verein-liste .verein-status-badge { display: none; }
                .verein-liste .verein-status-zeile { display: block; }
            }
        </style>
        <div class="list-group verein-liste">
            <?php foreach ($vereine as $v): ?>
                <?php // In der Liste nur „wartet auf Freigabe“ (sehen nur Verwalter); „aus öffentlichen Quellen“ steht auf der Vereinsseite
                $label = $v->status === \humhub\modules\vereine\models\Verein::STATUS_VORGESCHLAGEN ? $v->getStatusLabel() : '' ?>
                <a href="<?= Html::encode($v->getUrl()) ?>" class="list-group-item" style="display:flex;align-items:center;gap:12px">
                    <?= $this->render('_logo', ['verein' => $v, 'size' => 44]) ?>
                    <span style="flex:1;min-width:0">
                        <strong style="display:block"><?= Html::encode($v->name) ?></strong>
                        <small class="text-muted"><?= Html::encode($module->kategorieLabel($v->kategorie)) ?> · <?= Html::encode($module->ortLabel($v->ortsteil)) ?></small>
                        <?php if (isset($verwalter[$v->id])): ?>
                            <small class="text-muted" style="display:block"><i class="fa fa-user"></i> <?= Yii::t('VereineModule.base', 'Verwalter') ?>: <?= Html::encode($verwalter[$v->id]) ?></small>
                        <?php endif ?>
                        <?php if ($label): ?>
                            <small class="text-muted verein-status-zeile"><?= Html::encode($label) ?></small>
                        <?php endif ?>
                    </span>
                    <?php if ($label): ?>
                        <span class="badge verein-status-badge" style="background:#eee;color:#666;font-weight:normal"><?= Html::encode($label) ?></span>
                    <?php endif ?>
                </a>
            <?php endforeach ?>
        </div>
        <?php if (!$vereine): ?>
            <p><?= Yii::t('VereineModule.base', 'Nichts gefunden.') ?></p>
        <?php endif ?>
        <p style="margin-top:12px"><?= Yii::t('VereineModule.base', 'Dein Verein fehlt?') ?> <?= Html::a(Yii::t('VereineModule.base', 'Verein vorschlagen'), $space->createUrl('/vereine/verein/create')) ?></p>
    </div>
</div>
