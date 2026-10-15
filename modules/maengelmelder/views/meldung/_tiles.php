<?php
/** Gemeinsame Kartenvorgaben für Leaflet: Kacheln über unseren Server, Namensnennung OSM */

use yii\helpers\Url;

$tileUrl = str_replace(['ZZ', 'XX', 'YY'], ['{z}', '{x}', '{y}'],
    Url::to(['/maengelmelder/tile/index', 'z' => 'ZZ', 'x' => 'XX', 'y' => 'YY']));
?>
<script <?= \humhub\helpers\Html::nonce() ?>>
window.maengelTiles = function (map) {
    L.tileLayer(<?= json_encode($tileUrl) ?>, {
        minZoom: 12, maxZoom: 19,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a>-Mitwirkende'
    }).addTo(map);
    // Deutsche Beschriftungen für Screenreader und Tooltips
    var c = map.getContainer();
    [['.leaflet-control-zoom-in', 'Vergrößern'], ['.leaflet-control-zoom-out', 'Verkleinern']].forEach(function (z) {
        var b = c.querySelector(z[0]);
        if (b) { b.title = z[1]; b.setAttribute('aria-label', z[1]); }
    });
};
// Leaflet wird bei Seitenwechsel per pjax evtl. erst nachgeladen
window.maengelReady = function (fn) {
    (function wait() { (window.L && window.maengelTiles) ? fn() : setTimeout(wait, 50); })();
};
</script>
