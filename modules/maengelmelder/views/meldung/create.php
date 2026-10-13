<?php
/**
 * @var \humhub\modules\space\models\Space $space
 * @var bool $canPost
 * @var array $data
 * @var string[] $errors
 * @var \humhub\modules\maengelmelder\Module $module
 * @var \humhub\modules\maengelmelder\models\Mangel|null $bearbeiten Meldung, die der Bauhof ändert
 */

use humhub\helpers\Html;
use humhub\modules\file\widgets\FilePreview;
use humhub\modules\file\widgets\UploadButton;
use humhub\modules\file\widgets\UploadProgress;
use humhub\modules\maengelmelder\assets\MapAsset;

MapAsset::register($this);
?>
<div class="panel panel-default">
    <div class="panel-heading"><strong><?= $bearbeiten ? 'Meldung Nr. ' . $bearbeiten->getContentId() . ' bearbeiten' : 'Mangel melden' ?></strong>
        <?php if ($bearbeiten || Yii::$app->request->get('bauhof')): ?>
            <?= Html::a('<i class="fa fa-arrow-left"></i> Zurück zum Bauhof', $bearbeiten
                ? $space->createUrl('/maengelmelder/bauhof/mangel', ['id' => $bearbeiten->getContentId()])
                : $space->createUrl('/maengelmelder/bauhof/index'), ['class' => 'float-end small']) ?>
        <?php endif ?>
    </div>
    <div class="panel-body">
        <?php if (!$canPost): ?>
            <div class="alert alert-info">Um einen Mangel zu melden, treten Sie bitte zuerst dem Bereich „<?= Html::encode($space->name) ?>“ bei.</div>
        <?php else: ?>
            <?php if ($bearbeiten): ?>
            <p class="help-block">Änderungen erscheinen direkt im Beitrag. Neue Fotos werden hinzugefügt; vorhandene bleiben.</p>
            <?php else: ?>
            <p class="help-block">Schlagloch, defekte Laterne, wilder Müll? Beschreiben Sie den Mangel, markieren Sie den Ort auf der Karte
                und fügen Sie gern ein Foto hinzu. Die Meldung erscheint als Beitrag in diesem Bereich.
                Bitte keine Personen oder Kfz-Kennzeichen erkennbar fotografieren.</p>
            <?php endif ?>
            <?php if ($errors): ?>
                <div class="alert alert-danger"><?= implode('<br>', array_map([Html::class, 'encode'], $errors)) ?></div>
            <?php endif ?>

            <?= Html::beginForm('', 'post', ['id' => 'maengel-form']) ?>
            <div class="form-group">
                <label for="maengel-kategorie">Kategorie</label>
                <?= Html::dropDownList('Meldung[kategorie]', $data['kategorie'], array_combine($module->categories, $module->categories),
                    ['id' => 'maengel-kategorie', 'class' => 'form-control', 'prompt' => 'Bitte wählen …', 'required' => true]) ?>
            </div>
            <div class="form-group">
                <label for="maengel-beschreibung">Was ist kaputt?</label>
                <?= Html::textarea('Meldung[beschreibung]', $data['beschreibung'],
                    ['id' => 'maengel-beschreibung', 'class' => 'form-control', 'rows' => 4, 'maxlength' => 3000, 'required' => true,
                        'placeholder' => 'z. B. Tiefes Schlagloch am Fahrbahnrand, ca. 30 cm breit']) ?>
            </div>
            <div class="form-group">
                <label>Ort <small class="text-muted">– in die Karte klicken oder die Markierung verschieben</small></label>
                <div id="maengel-map" style="height:380px;border-radius:4px"></div>
                <div style="margin-top:6px">
                    <button type="button" class="btn btn-light btn-sm" id="maengel-locate"><i class="fa fa-crosshairs"></i> Meinen Standort verwenden</button>
                    <span id="maengel-coords" class="text-muted" style="margin-left:8px"></span>
                </div>
                <div id="maengel-nahe" class="alert alert-warning" style="display:none;margin-top:10px"></div>
                <?= Html::hiddenInput('Meldung[lat]', $data['lat'], ['id' => 'maengel-lat']) ?>
                <?= Html::hiddenInput('Meldung[lng]', $data['lng'], ['id' => 'maengel-lng']) ?>
            </div>
            <div class="form-group">
                <?= UploadButton::widget([
                    'id' => 'maengel-upload',
                    'label' => 'Foto hinzufügen',
                    'tooltip' => false,
                    'cssButtonClass' => 'btn-light',
                    'progress' => '#maengel-progress',
                    'preview' => '#maengel-preview',
                    'dropZone' => '#maengel-form',
                    'max' => 5,
                ]) ?>
                <?= UploadProgress::widget(['id' => 'maengel-progress']) ?>
                <?= FilePreview::widget(['id' => 'maengel-preview', 'edit' => true, 'options' => ['style' => 'margin-top:10px']]) ?>
            </div>
            <button type="submit" class="btn btn-primary"><?= $bearbeiten ? 'Speichern' : 'Meldung absenden' ?></button>
            <?= Html::endForm() ?>
        <?php endif ?>
    </div>
</div>

<?= $this->render('_tiles') ?>
<?php if ($canPost): ?>
<script <?= Html::nonce() ?>>
maengelReady(function () {
    var el = document.getElementById('maengel-map');
    if (!el || el._leaflet_id) return;
    var lat = document.getElementById('maengel-lat'), lng = document.getElementById('maengel-lng');
    var coords = document.getElementById('maengel-coords');
    var bounds = L.latLngBounds([<?= $module->bounds['south'] ?>, <?= $module->bounds['west'] ?>], [<?= $module->bounds['north'] ?>, <?= $module->bounds['east'] ?>]);
    var map = L.map(el, {maxBounds: bounds.pad(0.2)}).setView([<?= $module->centerLat ?>, <?= $module->centerLng ?>], 15);
    maengelTiles(map);
    var marker = null;
    // Schon gemeldet? Offene Meldungen in der Nähe anzeigen, damit man sich anschließen kann statt doppelt zu melden
    var nahe = document.getElementById('maengel-nahe'), naheLayer = L.layerGroup().addTo(map), naheTimer = null;
    var naheUrl = <?= json_encode($space->createUrl('/maengelmelder/meldung/nahe')) ?>;
    var bearbeiten = <?= $bearbeiten ? 'true' : 'false' ?>;
    var csrfParam = document.querySelector('meta[name=csrf-param]').content, csrfToken = document.querySelector('meta[name=csrf-token]').content;
    function esc(s) { var d = document.createElement('div'); d.textContent = s; return d.innerHTML; }
    function loadNahe(ll) {
        if (bearbeiten) return; // beim Bearbeiten keine „Schon gemeldet?“-Hinweise
        clearTimeout(naheTimer);
        naheTimer = setTimeout(function () {
            fetch(naheUrl + (naheUrl.indexOf('?') < 0 ? '?' : '&') + 'lat=' + ll.lat.toFixed(6) + '&lng=' + ll.lng.toFixed(6),
                {credentials: 'same-origin', headers: {'X-Requested-With': 'XMLHttpRequest'}})
                .then(function (r) { return r.ok ? r.json() : []; })
                .then(showNahe, function () { showNahe([]); });
        }, 250);
    }
    function showNahe(list) {
        naheLayer.clearLayers();
        if (!list.length) { nahe.style.display = 'none'; nahe.innerHTML = ''; return; }
        var html = '<strong><i class="fa fa-info-circle"></i> In der Nähe wurde schon etwas gemeldet.</strong> '
            + 'Ist es derselbe Mangel? Dann schließen Sie sich bitte an, statt ihn noch einmal zu melden.'
            + '<ul class="list-unstyled" style="margin:8px 0 0">';
        list.forEach(function (m) {
            L.circleMarker([m.lat, m.lng], {radius: 8, color: '#fff', weight: 2, fillColor: m.farbe, fillOpacity: 0.9})
                .bindPopup('<strong>' + esc(m.titel) + '</strong><br>' + esc(m.auszug)).addTo(naheLayer);
            html += '<li style="padding:6px 0;border-top:1px solid rgba(0,0,0,.08)">'
                + '<strong>' + esc(m.titel) + '</strong> <small class="text-muted">· ca. ' + m.entfernung + ' m entfernt · '
                + esc(m.statusLabel) + ' · gemeldet ' + esc(m.datum)
                + (m.auch ? ' · ' + m.auch + ' weitere betroffen' : '') + '</small><br>'
                + '<span>' + esc(m.auszug) + '</span><br>'
                + '<a href="' + esc(m.url) + '" target="_blank" rel="noopener">Ansehen</a>';
            if (m.eigene) {
                html += ' <small class="text-muted">(Ihre eigene Meldung)</small>';
            } else if (m.auchMeins) {
                html += ' <small class="text-muted"><i class="fa fa-check"></i> Sie haben sich bereits angeschlossen</small>';
            } else {
                html += ' <button type="button" class="btn btn-primary btn-sm" style="margin-left:8px" data-auch-url="'
                    + esc(m.auchUrl) + '">Betrifft mich auch</button>';
            }
            html += '</li>';
        });
        nahe.innerHTML = html + '</ul>';
        nahe.style.display = '';
    }
    // Eigenes Formular außerhalb des Meldeformulars absenden (Formulare dürfen nicht verschachtelt sein)
    nahe.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-auch-url]');
        if (!btn) return;
        var f = document.createElement('form');
        f.method = 'post';
        f.action = btn.getAttribute('data-auch-url');
        var i = document.createElement('input');
        i.type = 'hidden'; i.name = csrfParam; i.value = csrfToken;
        f.appendChild(i);
        document.body.appendChild(f);
        f.submit();
    });
    function setPoint(ll, pan) {
        if (!bounds.contains(ll)) { coords.textContent = 'Dieser Ort liegt außerhalb von Uffenheim.'; return; }
        if (!marker) {
            marker = L.marker(ll, {draggable: true}).addTo(map);
            marker.on('dragend', function () { setPoint(marker.getLatLng(), false); });
        } else {
            marker.setLatLng(ll);
        }
        lat.value = ll.lat.toFixed(6);
        lng.value = ll.lng.toFixed(6);
        coords.textContent = 'Markiert: ' + lat.value + ', ' + lng.value;
        loadNahe(ll);
        if (pan) map.setView(ll, Math.max(map.getZoom(), 17));
    }
    map.on('click', function (e) { setPoint(e.latlng, false); });
    if (lat.value && lng.value) setPoint(L.latLng(+lat.value, +lng.value), true);
    document.getElementById('maengel-locate').addEventListener('click', function () {
        if (!navigator.geolocation) { coords.textContent = 'Standort wird von diesem Browser nicht unterstützt.'; return; }
        coords.textContent = 'Standort wird ermittelt …';
        navigator.geolocation.getCurrentPosition(function (p) {
            setPoint(L.latLng(p.coords.latitude, p.coords.longitude), true);
        }, function () { coords.textContent = 'Standort nicht verfügbar – bitte in die Karte klicken.'; },
            {enableHighAccuracy: true, timeout: 10000});
    });
    document.getElementById('maengel-form').addEventListener('submit', function (e) {
        if (!lat.value) { e.preventDefault(); coords.textContent = 'Bitte zuerst den Ort auf der Karte markieren.'; el.scrollIntoView(); }
    });
});
</script>
<?php endif ?>
