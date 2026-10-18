<?php
/**
 * @var array $markers
 * @var array|null $focus [lat, lng]
 * @var \humhub\modules\maengelmelder\Module $module
 */

use humhub\helpers\Html;
use humhub\modules\maengelmelder\assets\MapAsset;
use humhub\modules\maengelmelder\models\MaengelStatus;

MapAsset::register($this);
\humhub\modules\maengelmelder\assets\StatusAsset::register($this);
?>
<div class="panel panel-default">
    <div class="panel-heading"><strong><?= Yii::t('MaengelmelderModule.base', 'Karte der Mängelmeldungen') ?></strong>
        <span class="text-muted">(<?= count($markers) ?>)</span></div>
    <div class="panel-body">
        <div class="mm-legende">
            <?php foreach (array_keys(MaengelStatus::STATUSES) as $key): ?>
                <?= MaengelStatus::etikett($key) ?>
            <?php endforeach ?>
            <label><input type="checkbox" id="maengel-erledigt"> <?= Yii::t('MaengelmelderModule.base', 'Erledigte und abgelehnte zeigen') ?></label>
        </div>
        <div id="maengel-karte" style="height:70vh;min-height:400px;border-radius:4px" role="region" aria-label="<?= Html::encode(Yii::t('MaengelmelderModule.base', 'Karte der Mängelmeldungen')) ?>"></div>
        <?php // Dieselben Meldungen als Liste: ohne Karte lesbar (Screenreader, Tastatur) ?>
        <details class="mm-liste">
            <summary><?= Yii::t('MaengelmelderModule.base', 'Meldungen als Liste anzeigen ({n})', ['n' => count($markers)]) ?></summary>
            <?php if ($markers === []): ?>
                <p class="text-muted"><?= Yii::t('MaengelmelderModule.base', 'Noch keine Meldungen.') ?></p>
            <?php else: ?>
            <div class="table-responsive" tabindex="0" role="region" aria-label="<?= Html::encode(Yii::t('MaengelmelderModule.base', 'Liste der Mängelmeldungen')) ?>">
                <table class="table table-sm">
                    <caption class="visually-hidden"><?= Yii::t('MaengelmelderModule.base', 'Mängelmeldungen, neueste zuerst') ?></caption>
                    <thead><tr><th scope="col"><?= Yii::t('MaengelmelderModule.base', 'Was') ?></th><th scope="col"><?= Yii::t('MaengelmelderModule.base', 'Ort') ?></th><th scope="col"><?= Yii::t('MaengelmelderModule.base', 'Stand') ?></th><th scope="col"><?= Yii::t('MaengelmelderModule.base', 'Gemeldet') ?></th></tr></thead>
                    <tbody>
                    <?php foreach ($markers as $m): ?>
                        <tr>
                            <td><?= $m['url'] ? Html::a(Html::encode($m['titel']), $m['url']) : Html::encode($m['titel']) ?></td>
                            <td><?= Html::encode($m['adresse'] !== '' ? $m['adresse'] : sprintf('%.5f, %.5f', $m['lat'], $m['lng'])) ?></td>
                            <td><?= Html::encode($m['statusLabel']) ?></td>
                            <td><?= Html::encode($m['datum']) ?></td>
                        </tr>
                    <?php endforeach ?>
                    </tbody>
                </table>
            </div>
            <?php endif ?>
        </details>
    </div>
</div>
<style <?= Html::nonce() ?>>
    .mm-liste { margin-top: 12px; }
    .mm-liste summary { cursor: pointer; font-weight: 600; padding: 8px 0; min-height: 24px; }
</style>

<?= $this->render('_tiles') ?>
<script <?= Html::nonce() ?>>
maengelReady(function () {
    var el = document.getElementById('maengel-karte');
    if (!el || el._leaflet_id) return;
    var markers = <?= json_encode($markers, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    var focus = <?= json_encode($focus) ?>;
    var loginUrl = <?= json_encode(\yii\helpers\Url::to(['/user/auth/login'])) ?>;
    // Übersetzte Texte (Platzhalter {name} ersetzt fmt)
    var t = <?= json_encode([
        'gemeldet' => Yii::t('MaengelmelderModule.base', 'gemeldet {datum}'),
        'einePerson' => Yii::t('MaengelmelderModule.base', '1 weitere Person betroffen'),
        'personen' => Yii::t('MaengelmelderModule.base', '{n} weitere Personen betroffen'),
        'zurMeldung' => Yii::t('MaengelmelderModule.base', 'Zur Meldung'),
        'anmelden' => Yii::t('MaengelmelderModule.base', 'Anmelden für Details'),
    ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    function fmt(s, p) { return s.replace(/\{(\w+)\}/g, function (a, k) { return p[k] !== undefined ? p[k] : a; }); }
    var map = L.map(el).setView([<?= $module->centerLat ?>, <?= $module->centerLng ?>], 14);
    maengelTiles(map);
    function esc(s) { var d = document.createElement('div'); d.textContent = s; return d.innerHTML; }
    var group = [], closed = [];
    var showClosed = document.getElementById('maengel-erledigt');
    markers.forEach(function (m) {
        var isFocus = focus && Math.abs(m.lat - focus[0]) < 1e-6 && Math.abs(m.lng - focus[1]) < 1e-6;
        var mk = L.circleMarker([m.lat, m.lng], {radius: 9, color: '#fff', weight: 2, fillColor: m.farbe, fillOpacity: 0.9})
            .bindPopup('<strong>' + esc(m.titel) + '</strong><br><span class="mm-status mm-status-' + esc(m.status) + '">' + esc(m.statusLabel) + '</span> · ' + esc(fmt(t.gemeldet, {datum: m.datum}))
                + (m.auch ? '<br>' + esc(m.auch === 1 ? t.einePerson : fmt(t.personen, {n: m.auch})) : '')
                + (m.url ? '<br><a href="' + esc(m.url) + '">' + esc(t.zurMeldung) + '</a>' : '<br><a href="' + esc(loginUrl) + '">' + esc(t.anmelden) + '</a>'));
        var isClosed = m.status === 'erledigt' || m.status === 'abgelehnt';
        if (!isClosed || isFocus) mk.addTo(map);
        if (isClosed && !isFocus) closed.push(mk);
        group.push(mk);
        if (isFocus) mk.openPopup();
    });
    showClosed.addEventListener('change', function () {
        closed.forEach(function (mk) { showClosed.checked ? mk.addTo(map) : mk.remove(); });
    });
    if (focus) {
        map.setView(focus, 18);
        if (!group.some(function (g) { return g.isPopupOpen && g.isPopupOpen(); })) L.marker(focus).addTo(map);
    } else if (group.length > 1) {
        map.fitBounds(L.featureGroup(group).getBounds().pad(0.2), {maxZoom: 17});
    }
});
</script>
