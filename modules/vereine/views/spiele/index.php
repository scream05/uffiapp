<?php
/**
 * @var \humhub\modules\vereine\models\Verein $verein
 * @var \humhub\modules\vereine\models\VereinMannschaft[] $mannschaften
 * @var \humhub\modules\vereine\models\VereinMannschaft|null $auswahl
 * @var \humhub\modules\vereine\models\VereinSpiel[] $kommend nächste zuerst
 * @var \humhub\modules\vereine\models\VereinSpiel[] $ergebnisse neueste zuerst
 * @var string|null $stand
 */

use humhub\helpers\Html;
use humhub\modules\vereine\components\Bfv;
use humhub\modules\vereine\controllers\SpieleController;

$space = $this->context->contentContainer;
$name = $verein->bfv_name ?: $verein->name;
$label = [];
foreach ($mannschaften as $m) {
    $label[$m->id] = $m->getLabel();
}
$filterUrl = fn(?string $id) => $space->createUrl('/vereine/spiele/index', array_filter(['verein' => $verein->id, 'mannschaft' => $id]));
$tagUeberschrift = function (string $datum): string {
    $t = (new DateTime($datum, new DateTimeZone('Europe/Berlin')))->getTimestamp();
    $text = Bfv::format($t, 'EEEEdMMMM');
    if ($datum === date('Y-m-d')) {
        $text = Yii::t('VereineModule.base', 'Heute') . ', ' . $text;
    } elseif ($datum === date('Y-m-d', strtotime('+1 day'))) {
        $text = Yii::t('VereineModule.base', 'Morgen') . ', ' . $text;
    }
    return $text;
};
$liste = function (array $spiele) use ($label, $tagUeberschrift) {
    $tag = null;
    foreach ($spiele as $s) {
        if ($s->datum !== $tag) {
            $tag = $s->datum;
            echo Html::tag('div', Html::encode($tagUeberschrift($tag)), ['class' => 'fvs-datum']);
        }
        echo $this->render('_spiel', ['spiel' => $s, 'label' => $label[$s->mannschaft_id] ?? '']);
    }
};
$tabelle = $auswahl ? $auswahl->getTabelleList() : [];
?>
<?= $this->render('_stil') ?>

<div class="panel panel-default">
    <div class="panel-body">
        <div style="display:flex;gap:14px;align-items:center;flex-wrap:wrap">
            <?= $this->render('@vereine/views/verein/_logo', ['verein' => $verein, 'size' => 56]) ?>
            <div style="flex:1;min-width:200px">
                <h1 style="margin:0 0 2px;font-size:1.4em"><?= Html::encode($name) ?></h1>
                <span class="text-muted"><i class="fa fa-futbol-o"></i> <?= Yii::t('VereineModule.base', 'Spiele & Ergebnisse aller Mannschaften') ?></span>
            </div>
        </div>
        <p style="margin:12px 0 0;display:flex;flex-wrap:wrap;gap:4px 16px">
            <?= Html::a('<i class="fa fa-users"></i> ' . Yii::t('VereineModule.base', 'Vereinsseite'), $verein->getUrl()) ?>
            <?php if ($verein->website): ?>
                <?= Html::a('<i class="fa fa-globe"></i> ' . Html::encode(preg_replace('#^https?://(www\.)?|/$#', '', $verein->website)), $verein->website, ['target' => '_blank', 'rel' => 'noopener']) ?>
            <?php endif ?>
            <?= Html::a('<i class="fa fa-external-link"></i> ' . Yii::t('VereineModule.base', 'Beim BFV'), Bfv::WEB . 'vereine/-/' . rawurlencode($verein->bfv_id), ['target' => '_blank', 'rel' => 'noopener']) ?>
        </p>
    </div>
</div>

<?php if (!$mannschaften): ?>
<div class="panel panel-default">
    <div class="panel-body"><p class="text-muted" style="margin:0"><?= Yii::t('VereineModule.base', 'Die Spiele werden gerade abgerufen. Schau bitte später noch einmal vorbei.') ?></p></div>
</div>
<?php else: ?>

<div class="panel panel-default">
    <div class="panel-body">
        <nav class="fvs-filter" aria-label="<?= Html::encode(Yii::t('VereineModule.base', 'Mannschaft wählen')) ?>">
            <?= Html::a(Yii::t('VereineModule.base', 'Alle Mannschaften'), $filterUrl(null), ['class' => 'btn btn-sm ' . ($auswahl ? 'btn-light' : 'btn-primary')]) ?>
            <?php foreach ($mannschaften as $m): ?>
                <?= Html::a(Html::encode($label[$m->id]), $filterUrl($m->id), ['class' => 'btn btn-sm ' . ($auswahl && $auswahl->id === $m->id ? 'btn-primary' : 'btn-light')]) ?>
            <?php endforeach ?>
        </nav>
        <?php if ($auswahl): ?>
            <p class="text-muted" style="margin:8px 0 0"><?= Html::encode($auswahl->name) ?><?= $auswahl->liga ? ' · ' . Html::encode($auswahl->liga) : '' ?></p>
        <?php endif ?>
    </div>
</div>

<div class="panel panel-default">
    <div class="panel-heading"><strong><?= Yii::t('VereineModule.base', 'Nächste Spiele') ?></strong></div>
    <div class="panel-body">
        <?php if ($kommend): ?>
            <?php $liste($kommend) ?>
        <?php else: ?>
            <p class="text-muted" style="margin:0"><?= $auswahl
                ? Yii::t('VereineModule.base', 'In dieser Saison stehen keine weiteren Spiele an.')
                : Yii::t('VereineModule.base', 'In den nächsten {n} Tagen stehen keine Spiele an.', ['n' => SpieleController::TAGE_VORAUS]) ?></p>
        <?php endif ?>
    </div>
</div>

<?php if ($tabelle): ?>
<div class="panel panel-default">
    <div class="panel-heading"><strong><?= Yii::t('VereineModule.base', 'Tabelle') ?></strong> <span class="text-muted"><?= Html::encode($auswahl->liga) ?></span></div>
    <div class="panel-body" style="overflow-x:auto">
        <table class="fvs-tabelle">
            <thead><tr>
                <th class="zahl">#</th><th><?= Yii::t('VereineModule.base', 'Mannschaft') ?></th>
                <th class="zahl" title="<?= Html::encode(Yii::t('VereineModule.base', 'Spiele')) ?>"><?= Yii::t('VereineModule.base', 'Sp.') ?></th>
                <th class="zahl breit" title="<?= Html::encode(Yii::t('VereineModule.base', 'Siege – Unentschieden – Niederlagen')) ?>"><?= Yii::t('VereineModule.base', 'S-U-N') ?></th>
                <th class="zahl" title="<?= Html::encode(Yii::t('VereineModule.base', 'Tordifferenz')) ?>"><?= Yii::t('VereineModule.base', 'Diff.') ?></th>
                <th class="zahl"><?= Yii::t('VereineModule.base', 'Pkt.') ?></th>
            </tr></thead>
            <tbody>
            <?php foreach ($tabelle as $z): ?>
                <tr class="<?= $z['eigen'] ? 'eigen' : '' ?>">
                    <td class="zahl"><?= (int)$z['platz'] ?></td>
                    <td><?= Html::encode($z['name']) ?></td>
                    <td class="zahl"><?= (int)$z['spiele'] ?></td>
                    <td class="zahl breit"><?= (int)$z['s'] ?>-<?= (int)$z['u'] ?>-<?= (int)$z['n'] ?></td>
                    <td class="zahl"><?= $z['diff'] > 0 ? '+' : '' ?><?= (int)$z['diff'] ?></td>
                    <td class="zahl"><strong><?= (int)$z['punkte'] ?></strong></td>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif ?>

<div class="panel panel-default">
    <div class="panel-heading"><strong><?= Yii::t('VereineModule.base', 'Ergebnisse') ?></strong>
        <?php if (!$auswahl): ?><span class="text-muted"><?= Yii::t('VereineModule.base', 'der letzten {n} Tage', ['n' => SpieleController::TAGE_ZURUECK]) ?></span><?php endif ?>
    </div>
    <div class="panel-body">
        <?php if ($ergebnisse): ?>
            <?php $liste($ergebnisse) ?>
        <?php else: ?>
            <p class="text-muted" style="margin:0"><?= Yii::t('VereineModule.base', 'Noch keine Ergebnisse.') ?></p>
        <?php endif ?>
    </div>
</div>

<?php if (!$auswahl): ?>
<div class="panel panel-default">
    <div class="panel-heading"><strong><?= Yii::t('VereineModule.base', 'Mannschaften') ?></strong></div>
    <div class="panel-body">
        <ul class="list-unstyled" style="margin:0">
            <?php foreach ($mannschaften as $m): $p = $m->getPlatz(); ?>
                <li style="padding:8px 0;border-top:1px solid var(--bs-border-color,#e5e5e5)">
                    <?= Html::a(Html::encode($label[$m->id]), $filterUrl($m->id), ['style' => 'font-weight:600']) ?>
                    <?php if ($m->liga): ?><span class="text-muted"> · <?= Html::encode($m->liga) ?></span><?php endif ?>
                    <?php if ($p !== null): ?>
                        <br><small><?= Yii::t('VereineModule.base', 'Platz {platz} von {von} · {punkte} Punkte', ['platz' => $p['platz'], 'von' => count($m->getTabelleList()), 'punkte' => $p['punkte']]) ?></small>
                    <?php endif ?>
                </li>
            <?php endforeach ?>
        </ul>
    </div>
</div>
<?php endif ?>
<?php endif ?>

<p class="text-muted" style="font-size:.85em;padding:0 4px">
    <?= Yii::t('VereineModule.base', 'Quelle: Bayerischer Fußball-Verband ({link}). Wir rufen die Daten stündlich ab, am Wochenende nachmittags öfter. Angaben ohne Gewähr.', ['link' => Html::a('bfv.de', Bfv::WEB . 'vereine/-/' . rawurlencode($verein->bfv_id), ['target' => '_blank', 'rel' => 'noopener'])]) ?>
    <?php if ($stand): ?><?= Yii::t('VereineModule.base', 'Stand: {zeit}', ['zeit' => Html::encode(Bfv::format(strtotime($stand), 'dMMMMHm'))]) ?><?php endif ?>
</p>
