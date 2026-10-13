<?php
/**
 * @var \humhub\modules\space\models\Space $space
 * @var array $zeilen
 * @var array $zaehler
 * @var array $filter
 * @var \humhub\modules\maengelmelder\models\Mitarbeiter[] $mitarbeiterListe
 * @var \humhub\modules\maengelmelder\Module $module
 */

use humhub\helpers\Html;
use humhub\modules\maengelmelder\assets\MapAsset;
use humhub\modules\maengelmelder\models\Auftrag;
use humhub\modules\maengelmelder\models\MaengelStatus;

MapAsset::register($this);
$url = fn(array $p = []) => $space->createUrl('/maengelmelder/bauhof/index', array_filter($p + $filter, fn($v) => $v !== ''));
$heute = date('Y-m-d');
$fmt = Yii::$app->formatter;
?>
<div class="panel panel-default">
    <div class="panel-body">
        <?= $this->render('_nav', ['space' => $space, 'aktiv' => 'index']) ?>

        <div class="d-flex flex-wrap gap-1 mb-2">
            <?php foreach (['offen' => 'Offen', 'alle' => 'Alle'] + array_map(fn($s) => $s[0], MaengelStatus::STATUSES) as $key => $label): ?>
                <?= Html::a(Html::encode($label) . ' <span class="opacity-75">' . ($zaehler[$key] ?? 0) . '</span>', $url(['status' => $key]),
                    ['class' => 'btn btn-sm ' . ($filter['status'] === $key ? 'btn-primary' : 'btn-light')]) ?>
            <?php endforeach ?>
        </div>

        <?= Html::beginForm($space->createUrl('/maengelmelder/bauhof/index'), 'get', ['class' => 'row g-2 mb-3']) ?>
        <?= Html::hiddenInput('status', $filter['status']) ?>
        <?= Html::hiddenInput('ansicht', $filter['ansicht']) ?>
        <div class="col-6 col-md-3">
            <?= Html::dropDownList('mitarbeiter', $filter['mitarbeiter'],
                ['' => 'Alle Mitarbeiter', '0' => '– nicht zugewiesen –'] + array_map(fn($m) => $m->name, array_column($mitarbeiterListe, null, 'id')),
                ['class' => 'form-control form-control-sm bauhof-auto']) ?>
        </div>
        <div class="col-6 col-md-3">
            <?= Html::dropDownList('kategorie', $filter['kategorie'], ['' => 'Alle Kategorien'] + array_combine($module->categories, $module->categories),
                ['class' => 'form-control form-control-sm bauhof-auto']) ?>
        </div>
        <div class="col-9 col-md-4">
            <?= Html::textInput('q', $filter['q'], ['class' => 'form-control form-control-sm', 'placeholder' => 'Suche: Nr., Text, Straße …']) ?>
        </div>
        <div class="col-3 col-md-2 d-flex gap-1">
            <button class="btn btn-sm btn-light flex-fill" title="Suchen"><i class="fa fa-search"></i></button>
            <?= Html::a('<i class="fa fa-' . ($filter['ansicht'] === 'karte' ? 'list' : 'map') . '"></i>',
                $url(['ansicht' => $filter['ansicht'] === 'karte' ? 'liste' : 'karte']),
                ['class' => 'btn btn-sm btn-light flex-fill', 'title' => $filter['ansicht'] === 'karte' ? 'Liste' : 'Karte']) ?>
        </div>
        <?= Html::endForm() ?>

        <?php if ($zeilen === []): ?>
            <p class="text-muted">Keine Meldungen für diese Auswahl.</p>
        <?php elseif ($filter['ansicht'] === 'karte'): ?>
            <div id="bauhof-karte" style="height:65vh;min-height:380px;border-radius:6px"></div>
        <?php else: ?>
            <table class="table table-sm bauhof-tabelle align-middle">
                <thead><tr>
                    <th>Nr.</th><th>Meldung</th><th>Status</th><th>Zuständig</th><th>Priorität / Frist</th><th>Aktivität</th>
                </tr></thead>
                <tbody>
                <?php foreach ($zeilen as $z):
                    /** @var \humhub\modules\maengelmelder\models\Mangel $m */
                    $m = $z['mangel'];
                    $a = $z['auftrag'];
                    $link = $space->createUrl('/maengelmelder/bauhof/mangel', ['id' => $m->getContentId()]);
                    $ueberfaellig = $a?->faellig && $a->faellig < $heute && !in_array($z['status']->status, MaengelStatus::CLOSED, true);
                    ?>
                    <tr class="bauhof-zeile<?= $ueberfaellig ? ' ueberfaellig' : '' ?>" data-href="<?= Html::encode($link) ?>">
                        <td><?= Html::a((string)$m->getContentId(), $link) ?></td>
                        <td>
                            <strong><?= Html::encode($m->kategorie) ?></strong>
                            <?php if ($z['ungelesen']): ?><span class="bauhof-neu" title="Neue Nachrichten"><?= $z['ungelesen'] ?></span><?php endif ?>
                            <br><small><?= Html::encode($m->getAuszug(90)) ?></small>
                            <br><small class="text-muted"><i class="fa fa-map-marker"></i> <?= Html::encode($m->getOrt()) ?>
                                <?= $z['bilder'] ? ' · <i class="fa fa-camera"></i> ' . $z['bilder'] : '' ?>
                                <?= $z['auch'] ? ' · <i class="fa fa-users"></i> +' . $z['auch'] : '' ?></small>
                        </td>
                        <td><?= $z['status']->getEtikett() ?></td>
                        <td><?= $a?->mitarbeiter ? Html::encode($a->mitarbeiter->name) : '<span class="text-muted">–</span>' ?></td>
                        <td>
                            <?php if ($a && $a->prioritaet !== 'normal'): ?>
                                <span class="bauhof-badge" style="background:<?= Auftrag::PRIORITAETEN[$a->prioritaet][1] ?>"><?= Html::encode($a->getPrioritaetLabel()) ?></span>
                            <?php endif ?>
                            <?php if ($a?->faellig): ?>
                                <small class="<?= $ueberfaellig ? 'text-danger fw-bold' : '' ?>">bis <?= $fmt->asDate($a->faellig, 'php:d.m.') ?></small>
                            <?php endif ?>
                        </td>
                        <td><small class="text-muted"><?= $fmt->asRelativeTime($z['aktivitaet']) ?></small></td>
                    </tr>
                <?php endforeach ?>
                </tbody>
            </table>
            <div class="bauhof-karten">
                <?php foreach ($zeilen as $z):
                    $m = $z['mangel'];
                    $a = $z['auftrag'];
                    $ueberfaellig = $a?->faellig && $a->faellig < $heute && !in_array($z['status']->status, MaengelStatus::CLOSED, true);
                    ?>
                    <a class="bauhof-karte<?= $ueberfaellig ? ' ueberfaellig' : '' ?>" href="<?= Html::encode($space->createUrl('/maengelmelder/bauhof/mangel', ['id' => $m->getContentId()])) ?>">
                        <div class="d-flex justify-content-between gap-2">
                            <strong>Nr. <?= $m->getContentId() ?> · <?= Html::encode($m->kategorie) ?>
                                <?php if ($z['ungelesen']): ?><span class="bauhof-neu"><?= $z['ungelesen'] ?></span><?php endif ?></strong>
                            <?= $z['status']->getEtikett() ?>
                        </div>
                        <small><?= Html::encode($m->getAuszug(100)) ?></small><br>
                        <small class="text-muted"><i class="fa fa-map-marker"></i> <?= Html::encode($m->getOrt()) ?></small><br>
                        <small class="text-muted"><i class="fa fa-user"></i> <?= $a?->mitarbeiter ? Html::encode($a->mitarbeiter->name) : 'nicht zugewiesen' ?>
                            <?= $a && $a->prioritaet !== 'normal' ? ' · ' . Html::encode($a->getPrioritaetLabel()) : '' ?>
                            <?= $a?->faellig ? ' · bis ' . $fmt->asDate($a->faellig, 'php:d.m.') : '' ?></small>
                    </a>
                <?php endforeach ?>
            </div>
        <?php endif ?>
    </div>
</div>

<script <?= Html::nonce() ?>>
$(document).off('change.bauhof').on('change.bauhof', '.bauhof-auto', function () { this.form.submit(); });
</script>
<?php if ($filter['ansicht'] === 'karte' && $zeilen): ?>
    <?= $this->render('../meldung/_tiles') ?>
    <?php
    $punkte = [];
    foreach ($zeilen as $z) {
        if ($z['mangel']->lat) {
            $punkte[] = ['lat' => $z['mangel']->lat, 'lng' => $z['mangel']->lng, 'farbe' => $z['status']->getColor(),
                'titel' => 'Nr. ' . $z['mangel']->getContentId() . ' · ' . $z['mangel']->kategorie,
                'text' => $z['mangel']->getAuszug(100) . ' (' . $z['status']->getLabel() . ($z['auftrag']?->mitarbeiter ? ', ' . $z['auftrag']->mitarbeiter->name : '') . ')',
                'url' => $space->createUrl('/maengelmelder/bauhof/mangel', ['id' => $z['mangel']->getContentId()])];
        }
    }
    ?>
    <script <?= Html::nonce() ?>>
    maengelReady(function () {
        var el = document.getElementById('bauhof-karte');
        if (!el || el._leaflet_id) return;
        var map = L.map(el).setView([<?= $module->centerLat ?>, <?= $module->centerLng ?>], 14);
        maengelTiles(map);
        var punkte = <?= json_encode($punkte, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>, b = [];
        function esc(s) { var d = document.createElement('div'); d.textContent = s; return d.innerHTML; }
        punkte.forEach(function (p) {
            L.circleMarker([p.lat, p.lng], {radius: 9, color: '#fff', weight: 2, fillColor: p.farbe, fillOpacity: .95}).addTo(map)
                .bindPopup('<strong>' + esc(p.titel) + '</strong><br>' + esc(p.text) + '<br><a href="' + esc(p.url) + '">Öffnen</a>');
            b.push([p.lat, p.lng]);
        });
        if (b.length > 1) map.fitBounds(b, {padding: [30, 30], maxZoom: 17}); else if (b.length) map.setView(b[0], 17);
    });
    </script>
<?php else: ?>
    <script <?= Html::nonce() ?>>
    $(document).off('click.bauhof').on('click.bauhof', '.bauhof-zeile', function (e) {
        if (!$(e.target).closest('a').length) window.location.href = $(this).data('href');
    });
    </script>
<?php endif ?>
