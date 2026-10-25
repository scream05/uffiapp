<?php
/**
 * @var \humhub\modules\vereine\models\Verein $verein
 * @var \humhub\modules\vereine\models\VereinMannschaft[] $mannschaften
 * @var \humhub\modules\vereine\models\VereinMannschaft|null $auswahl
 * @var string[] $label Mannschaft-ID => Bezeichnung
 * @var \humhub\modules\vereine\models\VereinSpiel[] $live laufende Spiele
 * @var \humhub\modules\vereine\models\VereinSpiel[] $kommend nächste zuerst
 * @var \humhub\modules\vereine\models\VereinSpiel[] $letzte letztes Spiel je Mannschaft, neueste zuerst
 * @var \humhub\modules\vereine\models\VereinSpiel[] $fruehere frühere Spiele der gewählten Mannschaft
 * @var string|null $stand
 */

use humhub\helpers\Html;
use humhub\modules\vereine\components\Bfv;

$space = $this->context->contentContainer;
$name = $verein->bfv_name ?: $verein->name;
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

<?php // Laufende Spiele; neu laden, solange heute noch Spiele anstehen oder laufen
$heute = $live || array_filter($kommend, fn($s) => $s->datum === date('Y-m-d') && $s->getZustand() === null);
$liveUrl = $space->createUrl('/vereine/spiele/index', array_filter(['verein' => $verein->id, 'mannschaft' => $auswahl?->id, 'teil' => 'live'])); ?>
<div id="fvs-live" data-url="<?= Html::encode($liveUrl) ?>" data-aktiv="<?= $heute ? 1 : 0 ?>">
    <?= $this->render('_live', ['live' => $live, 'label' => $label]) ?>
</div>
<?php if ($heute) {
    $this->registerJs(<<<JS
(function () {
    if (window.fvsLiveTimer) { return; } // läuft schon (PJAX), findet das neue Feld selbst
    function laden() {
        var box = document.getElementById('fvs-live');
        if (!box || box.dataset.aktiv !== '1') { clearInterval(window.fvsLiveTimer); window.fvsLiveTimer = null; return; }
        if (document.hidden) { return; }
        // Auf- bzw. zugeklappte Berichte beibehalten
        var zu = {};
        box.querySelectorAll('details.fvs-bericht').forEach(function (d, i) { zu[i] = !d.open; });
        fetch(box.dataset.url, {credentials: 'same-origin', headers: {'X-Requested-With': 'XMLHttpRequest'}})
            .then(function (r) { return r.ok ? r.text() : null; })
            .then(function (html) {
                if (html === null || !document.body.contains(box)) { return; }
                box.innerHTML = html;
                box.querySelectorAll('details.fvs-bericht').forEach(function (d, i) { if (zu[i]) { d.open = false; } });
            }).catch(function () {});
    }
    window.fvsLiveTimer = setInterval(laden, 60000);
    document.addEventListener('visibilitychange', function () { if (!document.hidden && window.fvsLiveTimer) { laden(); } });
})();
JS);
} ?>

<?php // Auf-/Zugeklappt merkt sich der Browser; ein Sprung per #anker öffnet den Bereich
$this->registerJs(<<<JS
document.querySelectorAll('details.fvs-klapp').forEach(function (d) {
    var key = 'fvs-zu-' + d.id;
    try { if (localStorage.getItem(key) === '1' && location.hash !== '#' + d.id) { d.open = false; } } catch (e) {}
    d.addEventListener('toggle', function () { try { d.open ? localStorage.removeItem(key) : localStorage.setItem(key, '1'); } catch (e) {} });
});
JS); ?>
<details class="panel panel-default fvs-klapp" id="fvs-naechste" open>
    <summary class="panel-heading"><strong><?= $auswahl ? Yii::t('VereineModule.base', 'Nächstes Spiel') : Yii::t('VereineModule.base', 'Nächste Spiele') ?></strong>
        <?php if (!$auswahl): ?><span class="text-muted"><?= Yii::t('VereineModule.base', 'je Mannschaft') ?></span><?php endif ?>
        <i class="fa fa-chevron-down fvs-pfeil" aria-hidden="true"></i></summary>
    <div class="panel-body">
        <?php if ($kommend): ?>
            <?php $liste($kommend) ?>
        <?php else: ?>
            <p class="text-muted" style="margin:0"><?= Yii::t('VereineModule.base', 'In dieser Saison stehen keine weiteren Spiele an.') ?></p>
        <?php endif ?>
    </div>
</details>

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

<details class="panel panel-default fvs-klapp" id="fvs-letzte" open>
    <summary class="panel-heading"><strong><?= $auswahl ? Yii::t('VereineModule.base', 'Letztes Spiel') : Yii::t('VereineModule.base', 'Letzte Spiele') ?></strong>
        <?php if (!$auswahl): ?><span class="text-muted"><?= Yii::t('VereineModule.base', 'je Mannschaft') ?></span><?php endif ?>
        <i class="fa fa-chevron-down fvs-pfeil" aria-hidden="true"></i></summary>
    <div class="panel-body">
        <?php if ($letzte): ?>
            <?php foreach ($letzte as $s): ?>
                <div class="fvs-letztes">
                    <?= $this->render('_spiel', ['spiel' => $s, 'label' => $label[$s->mannschaft_id] ?? '', 'mitDatum' => true]) ?>
                    <?= $this->render('_bericht', ['spiel' => $s]) ?>
                    <?php if (!$auswahl): ?>
                        <?= Html::a(Yii::t('VereineModule.base', 'Frühere Spiele: {mannschaft}', ['mannschaft' => Html::encode($label[$s->mannschaft_id] ?? '')]) . ' <i class="fa fa-angle-right"></i>',
                            $filterUrl($s->mannschaft_id) . '#fvs-frueher', ['class' => 'fvs-mehr']) ?>
                    <?php endif ?>
                </div>
            <?php endforeach ?>
        <?php else: ?>
            <p class="text-muted" style="margin:0"><?= Yii::t('VereineModule.base', 'Noch keine Ergebnisse.') ?></p>
        <?php endif ?>
    </div>
</details>

<?php if ($fruehere): ?>
<details class="panel panel-default fvs-klapp" id="fvs-frueher" open>
    <summary class="panel-heading"><strong><?= Yii::t('VereineModule.base', 'Frühere Spiele') ?></strong>
        <i class="fa fa-chevron-down fvs-pfeil" aria-hidden="true"></i></summary>
    <div class="panel-body">
        <?php foreach ($fruehere as $s): ?>
            <?= $this->render('_spiel', ['spiel' => $s, 'label' => $label[$s->mannschaft_id] ?? '', 'mitDatum' => true]) ?>
        <?php endforeach ?>
    </div>
</details>
<?php endif ?>

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
    <?= Yii::t('VereineModule.base', 'Quelle: Bayerischer Fußball-Verband ({link}). Wir gleichen Spielplan und Ergebnisse täglich ab; während eines Spiels holen wir den Liveticker jede Minute. Angaben ohne Gewähr.', ['link' => Html::a('bfv.de', Bfv::WEB . 'vereine/-/' . rawurlencode($verein->bfv_id), ['target' => '_blank', 'rel' => 'noopener'])]) ?>
    <?php if ($stand): ?><?= Yii::t('VereineModule.base', 'Stand: {zeit}', ['zeit' => Html::encode(Bfv::format(strtotime($stand), 'dMMMMHm'))]) ?><?php endif ?>
</p>
