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
};
// Leaflet wird bei Seitenwechsel per pjax evtl. erst nachgeladen
window.maengelReady = function (fn) {
    (function wait() { (window.L && window.maengelTiles) ? fn() : setTimeout(wait, 50); })();
};
</script>
