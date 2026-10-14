<?php
/**
 * @var \humhub\modules\space\models\Space $space
 * @var bool $canPost
 * @var array $data
 * @var string[] $errors
 * @var \humhub\modules\maengelmelder\Module $module
 * @var \humhub\modules\maengelmelder\models\Mangel|null $bearbeiten Meldung, die der Bauhof ändert
 * @var \humhub\modules\maengelmelder\models\MeldeKontakt|null $kontakt Kontaktangaben (nicht beim Bearbeiten)
 * @var string|null $danke Nr. der eben eingegangenen Gastmeldung
 */

use humhub\helpers\Html;
use humhub\modules\file\widgets\FilePreview;
use humhub\modules\file\widgets\UploadButton;
use humhub\modules\file\widgets\UploadProgress;
use humhub\modules\maengelmelder\assets\MapAsset;
use humhub\widgets\form\CaptchaField;
use yii\helpers\Url;

MapAsset::register($this);
$gast = Yii::$app->user->isGuest;
$feld = fn(string $a) => $kontakt && $kontakt->hasErrors($a) ? ' is-invalid' : '';
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
        <?php if ($danke !== null): ?>
            <div class="alert alert-success">
                <strong>Vielen Dank<?= $danke ? ', Ihre Meldung Nr. ' . (int)$danke . ' ist eingegangen' : ', Ihre Meldung ist eingegangen' ?>.</strong><br>
                Der Bauhof prüft sie und schaltet sie danach auf der Karte frei. Bei Rückfragen meldet er sich über die angegebenen Kontaktdaten.
            </div>
        <?php endif ?>
        <?php if (!$canPost && $gast): ?>
            <div class="alert alert-info">Um einen Mangel zu melden, melden Sie sich bitte an oder legen Sie ein kostenloses Konto an.
                <?= Html::a('Anmelden', ['/user/auth/login'], ['class' => 'alert-link', 'data-pjax-prevent' => 1]) ?></div>
        <?php elseif (!$canPost): ?>
            <div class="alert alert-info">Um einen Mangel zu melden, treten Sie bitte zuerst dem Bereich „<?= Html::encode($space->name) ?>“ bei.</div>
        <?php else: ?>
            <?php if ($bearbeiten): ?>
            <p class="help-block">Änderungen erscheinen direkt im Beitrag. Neue Fotos werden hinzugefügt; vorhandene bleiben.</p>
            <?php elseif ($gast): ?>
            <p class="help-block">Schlagloch, defekte Laterne, wilder Müll? Beschreiben Sie den Mangel, markieren Sie den Ort auf der Karte
                und fügen Sie gern ein Foto hinzu. Sie brauchen dafür kein Konto: Der Bauhof prüft Ihre Meldung und schaltet sie
                danach auf der Karte frei. Bitte keine Personen oder Kfz-Kennzeichen erkennbar fotografieren.</p>
            <?php else: ?>
            <p class="help-block">Schlagloch, defekte Laterne, wilder Müll? Beschreiben Sie den Mangel, markieren Sie den Ort auf der Karte
                und fügen Sie gern ein Foto hinzu. Die Meldung erscheint als Beitrag in diesem Bereich.
                Bitte keine Personen oder Kfz-Kennzeichen erkennbar fotografieren.</p>
            <?php endif ?>
            <?php if ($errors): ?>
                <div class="alert alert-danger"><?= implode('<br>', array_map([Html::class, 'encode'], $errors)) ?></div>
            <?php endif ?>

            <?= Html::beginForm('', 'post', ['id' => 'maengel-form'] + ($gast ? ['enctype' => 'multipart/form-data'] : [])) ?>
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
            <?php if ($gast): ?>
            <div class="form-group">
                <label for="maengel-fotos">Fotos <small class="text-muted">– freiwillig, höchstens <?= $module->gastFotos ?></small></label>
                <input type="file" name="fotos[]" id="maengel-fotos" class="form-control" accept="image/*" multiple
                       data-max="<?= $module->gastFotos ?>" data-max-mb="<?= $module->gastFotoMaxMb ?>">
                <small class="text-muted" id="maengel-fotos-hinweis"></small>
            </div>
            <?php else: ?>
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
            <?php endif ?>

            <?php if ($kontakt && $gast): ?>
            <fieldset class="maengel-kontakt">
                <legend>Ihre Kontaktdaten <small class="text-muted">– für Rückfragen des Bauhofs</small></legend>
                <p class="help-block">Ihre Angaben sehen nur die Bauhofleitung und die Administratoren dieser Plattform, nicht die Öffentlichkeit.
                    Sie werden nur für Rückfragen zu dieser Meldung verwendet und <?= round($module->kontaktAufbewahrungTage / 30) ?> Monate
                    nach ihrer Erledigung gelöscht.</p>
                <div class="row">
                    <div class="col-sm-6 form-group">
                        <label for="kontakt-vorname">Vorname</label>
                        <?= Html::activeTextInput($kontakt, 'vorname', ['id' => 'kontakt-vorname', 'class' => 'form-control' . $feld('vorname'),
                            'required' => true, 'maxlength' => 60, 'autocomplete' => 'given-name']) ?>
                    </div>
                    <div class="col-sm-6 form-group">
                        <label for="kontakt-nachname">Nachname</label>
                        <?= Html::activeTextInput($kontakt, 'nachname', ['id' => 'kontakt-nachname', 'class' => 'form-control' . $feld('nachname'),
                            'required' => true, 'maxlength' => 60, 'autocomplete' => 'family-name']) ?>
                    </div>
                </div>
                <div class="row">
                    <div class="col-sm-6 form-group">
                        <label for="kontakt-email">E-Mail</label>
                        <?= Html::activeInput('email', $kontakt, 'email', ['id' => 'kontakt-email', 'class' => 'form-control' . $feld('email'),
                            'maxlength' => 150, 'autocomplete' => 'email']) ?>
                    </div>
                    <div class="col-sm-6 form-group">
                        <label for="kontakt-telefon">Telefon</label>
                        <?= Html::activeInput('tel', $kontakt, 'telefon', ['id' => 'kontakt-telefon', 'class' => 'form-control' . $feld('telefon'),
                            'maxlength' => 30, 'autocomplete' => 'tel']) ?>
                    </div>
                </div>
                <p class="help-block">Bitte mindestens E-Mail oder Telefon angeben.</p>
                <?php if ($module->buergerInfoAktiv()): ?>
                <div class="form-group maengel-info">
                    <label>Über den Bearbeitungsstand informieren <small class="text-muted">– freiwillig, bei jeder Statusänderung</small></label>
                    <div class="form-check">
                        <?= Html::activeCheckbox($kontakt, 'info_mail', ['id' => 'kontakt-info-mail', 'class' => 'form-check-input' . $feld('info_mail'), 'label' => false]) ?>
                        <label class="form-check-label" for="kontakt-info-mail">per E-Mail<?= $gast ? '' : ' (' . Html::encode(Yii::$app->user->getIdentity()->email) . ')' ?></label>
                    </div>
                    <?php if ($module->buergerWhatsappAktiv()): ?>
                    <div class="form-check">
                        <?= Html::activeCheckbox($kontakt, 'info_whatsapp', ['id' => 'kontakt-info-whatsapp', 'class' => 'form-check-input' . $feld('info_whatsapp'), 'label' => false]) ?>
                        <label class="form-check-label" for="kontakt-info-whatsapp">per WhatsApp an meine Handynummer
                            <small class="text-muted">– Sie können darauf antworten, Ihre Antwort geht an den Bauhof. Abbestellen: STOPP schreiben.</small></label>
                    </div>
                    <?php endif ?>
                </div>
                <?php endif ?>

                <div class="maengel-feld-website" aria-hidden="true">
                    <label for="kontakt-website">Website (bitte leer lassen)</label>
                    <?= Html::activeTextInput($kontakt, 'website', ['id' => 'kontakt-website', 'tabindex' => -1, 'autocomplete' => 'off']) ?>
                </div>
                <div class="form-check form-group">
                    <?= Html::activeCheckbox($kontakt, 'datenschutz', ['id' => 'kontakt-datenschutz', 'class' => 'form-check-input' . $feld('datenschutz'),
                        'label' => false, 'uncheck' => '0', 'required' => true]) ?>
                    <label class="form-check-label" for="kontakt-datenschutz">Ich bin einverstanden, dass meine Angaben zur Bearbeitung dieser
                        Meldung gespeichert werden. Einzelheiten in der
                        <?= Html::a('Datenschutzerklärung', Url::to(['/legal/page/view', 'pageKey' => 'privacy']), ['target' => '_blank', 'rel' => 'noopener']) ?>.</label>
                </div>
                <div class="form-group">
                    <?= CaptchaField::widget(['model' => $kontakt, 'attribute' => 'captcha']) ?>
                </div>
            </fieldset>
            <?php elseif ($kontakt): ?>
            <?php $ich = Yii::$app->user->getIdentity() ?>
            <fieldset class="maengel-kontakt">
                <legend>Ihre Kontaktdaten <small class="text-muted">– für Rückfragen des Bauhofs</small></legend>
                <p style="margin-bottom:8px"><i class="fa fa-user"></i> <?= Html::encode($ich->displayName) ?>
                    &nbsp; <i class="fa fa-envelope-o"></i> <?= Html::encode($ich->email) ?></p>
                <div class="form-group" style="max-width:360px">
                    <label for="kontakt-telefon">Telefon <small class="text-muted">– freiwillig, für WhatsApp-Infos eine Handynummer</small></label>
                    <?= Html::activeInput('tel', $kontakt, 'telefon', ['id' => 'kontakt-telefon', 'class' => 'form-control' . $feld('telefon'),
                        'maxlength' => 30, 'autocomplete' => 'tel']) ?>
                    <small class="text-muted">Nur für Rückfragen zu dieser Meldung; sieht nur der Bauhof.</small>
                </div>
                <?php if ($module->buergerInfoAktiv()): ?>
                <div class="form-group maengel-info">
                    <label>Über den Bearbeitungsstand informieren <small class="text-muted">– freiwillig, bei jeder Statusänderung</small></label>
                    <div class="form-check">
                        <?= Html::activeCheckbox($kontakt, 'info_mail', ['id' => 'kontakt-info-mail', 'class' => 'form-check-input' . $feld('info_mail'), 'label' => false]) ?>
                        <label class="form-check-label" for="kontakt-info-mail">per E-Mail<?= $gast ? '' : ' (' . Html::encode(Yii::$app->user->getIdentity()->email) . ')' ?></label>
                    </div>
                    <?php if ($module->buergerWhatsappAktiv()): ?>
                    <div class="form-check">
                        <?= Html::activeCheckbox($kontakt, 'info_whatsapp', ['id' => 'kontakt-info-whatsapp', 'class' => 'form-check-input' . $feld('info_whatsapp'), 'label' => false]) ?>
                        <label class="form-check-label" for="kontakt-info-whatsapp">per WhatsApp an meine Handynummer (oben)
                            <small class="text-muted">– Sie können darauf antworten, Ihre Antwort geht an den Bauhof. Abbestellen: STOPP schreiben.</small></label>
                    </div>
                    <?php endif ?>
                </div>
                <?php endif ?>

            </fieldset>
            <?php endif ?>
            <button type="submit" class="btn btn-primary"><?= $bearbeiten ? 'Speichern' : 'Meldung absenden' ?></button>
            <?= Html::endForm() ?>
        <?php endif ?>
    </div>
</div>

<style <?= Html::nonce() ?>>
    .maengel-kontakt { margin: 18px 0 10px; padding-top: 14px; border-top: 1px solid var(--hh-border-color, #e5e5e5); }
    .maengel-kontakt legend { font-size: 1rem; font-weight: 600; margin-bottom: 6px; }
    .maengel-feld-website { position: absolute; left: -10000px; width: 1px; height: 1px; overflow: hidden; }
</style>
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
    var bearbeiten = <?= $bearbeiten ? 'true' : 'false' ?>, gast = <?= $gast ? 'true' : 'false' ?>;
    var fotos = document.getElementById('maengel-fotos');
    if (fotos) {
        fotos.addEventListener('change', function () {
            var max = +fotos.dataset.max, maxMb = +fotos.dataset.maxMb, zuGross = [].some.call(fotos.files, function (f) { return f.size > maxMb * 1048576; });
            var text = fotos.files.length > max ? 'Bitte höchstens ' + max + ' Fotos auswählen.' : (zuGross ? 'Ein Foto ist größer als ' + maxMb + ' MB.' : '');
            document.getElementById('maengel-fotos-hinweis').textContent = text;
            fotos.setCustomValidity(text);
        });
    }
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
                + (m.url ? '<a href="' + esc(m.url) + '" target="_blank" rel="noopener">Ansehen</a>' : '');
            if (gast) {
                html += ' <small class="text-muted">Ist es derselbe Mangel? Dann ist keine neue Meldung nötig.</small>';
            } else if (m.eigene) {
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
    // Standort: erst genau (GPS), bei Zeitüberschreitung noch einmal ungenauer (WLAN/Funkzelle), Fehler verständlich melden
    var locateBtn = document.getElementById('maengel-locate');
    function locateFehler(err) {
        locateBtn.disabled = false;
        if (!window.isSecureContext) {
            coords.textContent = 'Die Ortung geht nur über eine sichere Verbindung (https).';
        } else if (err && err.code === 1) {
            coords.textContent = 'Der Standortzugriff ist nicht erlaubt. Bitte in den Einstellungen des Browsers bzw. der App '
                + 'und des Handys (Ortungsdienste) erlauben – oder den Ort in der Karte antippen.';
        } else if (err && err.code === 3) {
            coords.textContent = 'Der Standort konnte nicht rechtzeitig ermittelt werden. Bitte noch einmal versuchen oder in die Karte tippen.';
        } else {
            coords.textContent = 'Standort nicht verfügbar (Ortungsdienste aus?) – bitte in die Karte tippen.';
        }
    }
    function locateTreffer(p) {
        locateBtn.disabled = false;
        var ll = L.latLng(p.coords.latitude, p.coords.longitude);
        if (!bounds.contains(ll)) {
            coords.textContent = 'Ihr Standort liegt außerhalb von Uffenheim. Bitte den Ort des Mangels in der Karte antippen.';
            return;
        }
        setPoint(ll, true);
    }
    locateBtn.addEventListener('click', function () {
        if (!navigator.geolocation) { coords.textContent = 'Standort wird von diesem Browser nicht unterstützt – bitte in die Karte tippen.'; return; }
        coords.textContent = 'Standort wird ermittelt …';
        locateBtn.disabled = true;
        navigator.geolocation.getCurrentPosition(locateTreffer, function (err) {
            if (err && (err.code === 3 || err.code === 2)) {
                coords.textContent = 'Standort wird ermittelt (ungenauer) …';
                navigator.geolocation.getCurrentPosition(locateTreffer, locateFehler,
                    {enableHighAccuracy: false, timeout: 15000, maximumAge: 120000});
            } else {
                locateFehler(err);
            }
        }, {enableHighAccuracy: true, timeout: 12000, maximumAge: 30000});
    });
    document.getElementById('maengel-form').addEventListener('submit', function (e) {
        if (!lat.value) { e.preventDefault(); coords.textContent = 'Bitte zuerst den Ort auf der Karte markieren.'; el.scrollIntoView(); }
    });
});
</script>
<?php endif ?>
