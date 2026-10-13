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
?>
<div class="panel panel-default">
    <div class="panel-heading"><strong>Karte der Mängelmeldungen</strong>
        <span class="text-muted">(<?= count($markers) ?>)</span></div>
    <div class="panel-body">
        <div style="margin-bottom:8px">
            <?php foreach (MaengelStatus::STATUSES as [$label, $color]): ?>
                <span style="display:inline-block;margin-right:12px;white-space:nowrap">
                    <span style="display:inline-block;width:12px;height:12px;border-radius:50%;background:<?= $color ?>;vertical-align:-1px"></span>
                    <?= Html::encode($label) ?></span>
            <?php endforeach ?>
            <label style="font-weight:normal;margin-left:8px;white-space:nowrap">
                <input type="checkbox" id="maengel-erledigt"> Erledigte und abgelehnte zeigen</label>
        </div>
        <div id="maengel-karte" style="height:70vh;min-height:400px;border-radius:4px"></div>
    </div>
</div>

<?= $this->render('_tiles') ?>
<script <?= Html::nonce() ?>>
maengelReady(function () {
    var el = document.getElementById('maengel-karte');
    if (!el || el._leaflet_id) return;
    var markers = <?= json_encode($markers, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    var focus = <?= json_encode($focus) ?>;
    var loginUrl = <?= json_encode(\yii\helpers\Url::to(['/user/auth/login'])) ?>;
    var map = L.map(el).setView([<?= $module->centerLat ?>, <?= $module->centerLng ?>], 14);
    maengelTiles(map);
    function esc(s) { var d = document.createElement('div'); d.textContent = s; return d.innerHTML; }
    var group = [], closed = [];
    var showClosed = document.getElementById('maengel-erledigt');
    markers.forEach(function (m) {
        var isFocus = focus && Math.abs(m.lat - focus[0]) < 1e-6 && Math.abs(m.lng - focus[1]) < 1e-6;
        var mk = L.circleMarker([m.lat, m.lng], {radius: 9, color: '#fff', weight: 2, fillColor: m.farbe, fillOpacity: 0.9})
            .bindPopup('<strong>' + esc(m.titel) + '</strong><br>' + esc(m.statusLabel) + ' · gemeldet ' + esc(m.datum)
                + (m.auch ? '<br>' + (m.auch === 1 ? '1 weitere Person' : m.auch + ' weitere Personen') + ' betroffen' : '')
                + (m.url ? '<br><a href="' + esc(m.url) + '">Zur Meldung</a>' : '<br><a href="' + esc(loginUrl) + '">Anmelden für Details</a>'));
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
