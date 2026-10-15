<?php
/**
 * Karte der Betriebe (Kacheln über den Server des Mängelmelders, ohne Verbindung der Besucher zu OpenStreetMap)
 * @var array $punkte
 * @var string $q
 * @var string $kategorie
 * @var string $ortsteil
 * @var int $ohneOrt
 * @var \humhub\modules\gewerbe\Module $module
 */

use humhub\helpers\Html;
use humhub\modules\maengelmelder\assets\MapAsset;

MapAsset::register($this);
$space = $this->context->contentContainer;
$mm = Yii::$app->getModule('maengelmelder');
$fokus = (int)Yii::$app->request->get('fokus');
?>
<div class="panel panel-default">
    <div class="panel-heading"><strong>Gewerbe &amp; Einkaufen – Karte</strong></div>
    <div class="panel-body">
        <?= $this->render('_ansicht', ['aktiv' => 'karte', 'q' => $q, 'kategorie' => $kategorie, 'ortsteil' => $ortsteil]) ?>
        <?php if ($kategorie !== '' || $ortsteil !== '' || $q !== ''): ?>
            <p class="text-muted">Gefiltert: <?= Html::encode(implode(' · ', array_filter([$kategorie, $ortsteil, $q !== '' ? "„{$q}“" : '']))) ?></p>
        <?php endif ?>
        <div id="gw-karte" style="height:65vh;min-height:380px;border-radius:12px" role="region" aria-label="Karte der Betriebe"></div>
        <p class="help-block" style="margin-top:8px"><?= count($punkte) ?> Betriebe auf der Karte<?= $ohneOrt ? ", $ohneOrt ohne bekannten Ort (nur in der Liste)" : '' ?>.
            Punkte: <span style="color:#3d5a06;font-weight:600">grün = jetzt geöffnet</span>, <span style="color:#8a2a20;font-weight:600">rot = geschlossen</span>,
            blau = keine Öffnungszeiten hinterlegt. Dieselben Betriebe stehen auch in der <?= Html::a('Liste', $space->createUrl('/gewerbe/betrieb/index', array_filter(['q' => $q, 'kategorie' => $kategorie, 'ortsteil' => $ortsteil]))) ?>.</p>
    </div>
</div>
<?= $this->render('@maengelmelder/views/meldung/_tiles') ?>
<script <?= Html::nonce() ?>>
maengelReady(function () {
    var el = document.getElementById('gw-karte');
    if (!el || el._leaflet_id) return;
    var punkte = <?= json_encode($punkte, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    var fokus = <?= json_encode($fokus ? \humhub\modules\gewerbe\models\Betrieb::findOne($fokus)?->getUrl() : null) ?>;
    var map = L.map(el).setView([<?= $mm ? $mm->centerLat : 49.5455 ?>, <?= $mm ? $mm->centerLng : 10.2293 ?>], 15);
    maengelTiles(map);
    function esc(s) { var d = document.createElement('div'); d.textContent = s; return d.innerHTML; }
    var gruppe = [], offen = null;
    punkte.forEach(function (p) {
        var farbe = p.offen === true ? '#5e7f0a' : (p.offen === false ? '#8a2a20' : '#446e88');
        var mk = L.circleMarker([p.lat, p.lng], {radius: 9, color: '#fff', weight: 2, fillColor: farbe, fillOpacity: 0.95})
            .bindPopup('<strong><a href="' + esc(p.url) + '">' + esc(p.name) + '</a></strong><br>' + esc(p.kategorie)
                + (p.adresse ? '<br>' + esc(p.adresse) : '') + (p.stand ? '<br><strong>' + esc(p.stand) + '</strong>' : ''))
            .addTo(map);
        gruppe.push(mk);
        if (fokus && p.url === fokus) offen = mk;
    });
    if (offen) {
        map.setView(offen.getLatLng(), 18);
        offen.openPopup();
    } else if (gruppe.length > 1) {
        map.fitBounds(L.featureGroup(gruppe).getBounds().pad(0.15), {maxZoom: 17});
    } else if (gruppe.length === 1) {
        map.setView(gruppe[0].getLatLng(), 17);
    }
});
</script>
