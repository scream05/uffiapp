<?php
/**
 * @var \humhub\modules\gewerbe\models\Gutschein $g
 * @var string|null $grund null = jetzt einlösbar
 * @var bool $canManage
 * @var \humhub\modules\gewerbe\models\Einloesung|null $letzte Einlösung der letzten Minuten (Bestätigung noch einmal zeigen)
 */

use humhub\helpers\Html;
use humhub\modules\gewerbe\models\Gutschein;

$space = $this->context->contentContainer;
$b = $g->betrieb;
$f = Yii::$app->formatter;
?>
<?= $this->render('_stil') ?>
<div class="panel panel-default">
    <div class="panel-body">
        <p><?= Html::a('<i class="fa fa-arrow-left" aria-hidden="true"></i> ' . Yii::t('GewerbeModule.base', 'Alle Gutscheine'), $space->createUrl('/gewerbe/gutschein/index')) ?></p>
        <div style="display:flex;gap:14px;align-items:center">
            <?= $this->render('@gewerbe/views/betrieb/_logo', ['betrieb' => $b, 'size' => 56]) ?>
            <div style="min-width:0">
                <div class="text-muted"><?= Yii::t('GewerbeModule.base', 'Gutschein von') ?></div>
                <strong><?= Html::a(Html::encode($b->name), $b->getUrl()) ?></strong>
            </div>
        </div>
        <h1 class="gs-gross" style="margin-top:14px"><?= Html::encode($g->titel) ?></h1>
        <?php if ($g->bedingungen): ?>
            <p style="white-space:pre-line"><?= Html::encode($g->bedingungen) ?></p>
        <?php endif ?>
        <ul class="list-unstyled text-muted" style="line-height:1.8">
            <li><i class="fa fa-calendar fa-fw" aria-hidden="true"></i> <?= $g->gueltig_ab > date('Y-m-d')
                ? Yii::t('GewerbeModule.base', 'Gültig vom {von} bis {bis}', ['von' => $f->asDate($g->gueltig_ab, 'medium'), 'bis' => $f->asDate($g->gueltig_bis, 'medium')])
                : Yii::t('GewerbeModule.base', 'Gültig bis {bis}', ['bis' => $f->asDate($g->gueltig_bis, 'medium')]) ?></li>
            <li><i class="fa fa-user fa-fw" aria-hidden="true"></i> <?= Yii::t('GewerbeModule.base', 'Pro Person {wie_oft}', ['wie_oft' => Html::encode(Gutschein::proPersonLabels()[$g->pro_person] ?? '')]) ?></li>
            <?php if (($rest = $g->restMenge()) !== null): ?>
                <li><i class="fa fa-cubes fa-fw" aria-hidden="true"></i> <?= Yii::t('GewerbeModule.base', 'Noch {rest} von {gesamt}', ['rest' => $rest, 'gesamt' => $g->limit_gesamt]) ?></li>
            <?php endif ?>
            <?php if ($g->nur_geoeffnet): ?>
                <li><i class="fa fa-clock-o fa-fw" aria-hidden="true"></i> <?= Yii::t('GewerbeModule.base', 'Nur während der Öffnungszeiten') ?><?= ($stand = $b->getStand(true)) ? ' · ' . Html::encode($stand['text']) : '' ?></li>
            <?php endif ?>
            <?php if ($b->adresse): ?>
                <li><i class="fa fa-map-marker fa-fw" aria-hidden="true"></i> <?= Html::encode($b->adresse) ?></li>
            <?php endif ?>
        </ul>
    </div>
</div>

<div class="panel panel-default">
    <div class="panel-heading"><strong><?= Yii::t('GewerbeModule.base', 'Einlösen') ?></strong></div>
    <div class="panel-body">
        <?php if ($letzte): ?>
            <p><?= Html::a('<i class="fa fa-check-circle" aria-hidden="true"></i> ' . Yii::t('GewerbeModule.base', 'Deine Bestätigung von {zeit} Uhr wieder anzeigen', ['zeit' => $f->asTime($letzte->created_at, 'short')]),
                    $space->createUrl('/gewerbe/gutschein/bestaetigung', ['id' => $letzte->id]), ['class' => 'btn btn-primary']) ?></p>
        <?php endif ?>
        <?php if ($grund !== null && Yii::$app->user->isGuest && $g->status === Gutschein::STATUS_AKTIV && !$g->istAbgelaufen()): ?>
            <p><?= Yii::t('GewerbeModule.base', 'Zum Einlösen brauchst du ein kostenloses Konto bei Rund um Uffni.') ?></p>
            <?= Html::a(Yii::t('GewerbeModule.base', 'Anmelden oder registrieren'), ['/user/auth/login'], ['class' => 'btn btn-primary', 'data-pjax-prevent' => true]) ?>
        <?php elseif ($grund !== null): ?>
            <p class="alert alert-info" style="margin:0"><?= Html::encode($grund) ?></p>
        <?php else: ?>
            <ol class="gs-schritte">
                <li><?= Yii::t('GewerbeModule.base', 'Geh zu {name} und sag an der Kasse, dass du den Gutschein nutzen möchtest.', ['name' => '<strong>' . Html::encode($b->name) . '</strong>']) ?></li>
                <li><?= Yii::t('GewerbeModule.base', 'Scanne dort den <strong>QR-Code auf dem Aufsteller</strong> mit der Kamera deines Handys.') ?></li>
                <li><?= Yii::t('GewerbeModule.base', 'Tippe auf <strong>„Jetzt einlösen“</strong> und zeig den Bildschirm dem Personal.') ?></li>
            </ol>
            <details>
                <summary style="cursor:pointer"><?= Yii::t('GewerbeModule.base', 'Kein QR-Scanner? Nummer vom Aufsteller eingeben') ?></summary>
                <?= Html::beginForm($space->createUrl('/gewerbe/gutschein/einloesen', ['id' => $g->id]), 'post', ['style' => 'margin-top:10px']) ?>
                    <label for="gs-nummer"><?= Yii::t('GewerbeModule.base', 'Nummer vom Aufsteller an der Kasse (4 Ziffern)') ?></label>
                    <div class="gs-nummer">
                        <?= Html::textInput('nummer', '', ['id' => 'gs-nummer', 'class' => 'form-control', 'inputmode' => 'numeric',
                            'pattern' => '[0-9]{4}', 'maxlength' => 4, 'autocomplete' => 'off', 'required' => true]) ?>
                        <button type="submit" class="btn btn-primary" data-confirm="<?= Html::encode(Yii::t('GewerbeModule.base', 'Erst an der Kasse tippen! Danach ist der Gutschein verbraucht.')) ?>"><?= Yii::t('GewerbeModule.base', 'Jetzt einlösen') ?></button>
                    </div>
                <?= Html::endForm() ?>
            </details>
        <?php endif ?>
    </div>
</div>

<?php if ($canManage): ?>
<div class="panel panel-default">
    <div class="panel-body" style="display:flex;flex-wrap:wrap;gap:6px">
        <?= Html::a('<i class="fa fa-pencil" aria-hidden="true"></i> ' . Yii::t('GewerbeModule.base', 'Bearbeiten'), $space->createUrl('/gewerbe/gutschein/edit', ['id' => $g->id]), ['class' => 'btn btn-primary btn-sm']) ?>
        <?= Html::a('<i class="fa fa-bar-chart" aria-hidden="true"></i> ' . Yii::t('GewerbeModule.base', 'Gutscheine und Einlösungen'), $space->createUrl('/gewerbe/gutschein/verwaltung', ['betrieb' => $b->id]), ['class' => 'btn btn-light btn-sm']) ?>
    </div>
</div>
<?php endif ?>
