<?php
/**
 * @var \humhub\modules\space\models\Space $space
 * @var \humhub\modules\maengelmelder\models\Mangel $mangel
 * @var \humhub\modules\maengelmelder\models\Auftrag $auftrag
 * @var \humhub\modules\maengelmelder\models\MaengelStatus $status
 * @var int $auch
 * @var \humhub\modules\maengelmelder\models\Mitarbeiter[] $mitarbeiterListe
 * @var \humhub\modules\maengelmelder\models\BauhofNachricht[] $verlauf
 * @var \humhub\modules\maengelmelder\models\MaengelKontakt|null $kontakt
 * @var bool $entwurf Gastmeldung, noch nicht freigegeben
 */

use humhub\helpers\Html;
use humhub\modules\maengelmelder\assets\MapAsset;
use humhub\modules\maengelmelder\models\Auftrag;
use humhub\modules\maengelmelder\models\MaengelStatus;

MapAsset::register($this);
$id = $mangel->getContentId();
$post = $mangel->post;
$fmt = Yii::$app->formatter;
$m = $auftrag->mitarbeiter;
$mitarbeiter = array_map(fn($x) => $x->name, array_column($mitarbeiterListe, null, 'id'));
if ($m && !isset($mitarbeiter[$m->id])) {
    $mitarbeiter[$m->id] = $m->name . ' (inaktiv)';
}
?>
<div class="panel panel-default">
    <div class="panel-body">
        <?= $this->render('_nav', ['space' => $space, 'aktiv' => 'mangel']) ?>
        <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
            <h2 class="h5 m-0 me-auto">Nr. <?= $id ?> · <?= Html::encode($mangel->kategorie) ?>
                <?= $status->getEtikett() ?></h2>
            <?= Html::a('<i class="fa fa-pencil"></i> Bearbeiten', $space->createUrl('/maengelmelder/meldung/bearbeiten', ['id' => $id]), ['class' => 'btn btn-sm btn-light']) ?>
            <?php if (!$entwurf): ?>
            <?= Html::a('<i class="fa fa-external-link"></i> Beitrag', $post->content->getUrl(), ['class' => 'btn btn-sm btn-light']) ?>
            <?php endif ?>
            <?= Html::beginForm($space->createUrl('/maengelmelder/bauhof/loeschen', ['id' => $id]), 'post', ['class' => 'd-inline', 'id' => 'bauhof-loeschen']) ?>
            <button class="btn btn-sm btn-light text-danger" title="Meldung löschen"><i class="fa fa-trash"></i></button>
            <?= Html::endForm() ?>
        </div>

        <?php if ($entwurf): ?>
            <div class="alert alert-warning d-flex flex-wrap align-items-center gap-2">
                <div class="me-auto"><strong>Gastmeldung ohne Konto – noch nicht freigegeben.</strong><br>
                    Sie ist erst nach der Freigabe im Bereich und auf der Karte sichtbar. Zuweisen und Status setzen geht danach.</div>
                <?= Html::beginForm($space->createUrl('/maengelmelder/bauhof/freigeben', ['id' => $id]), 'post', ['class' => 'd-inline']) ?>
                <button class="btn btn-sm btn-primary"><i class="fa fa-check"></i> Freigeben</button>
                <?= Html::endForm() ?>
                <?= Html::beginForm($space->createUrl('/maengelmelder/bauhof/loeschen', ['id' => $id]), 'post', ['class' => 'd-inline', 'id' => 'bauhof-ablehnen']) ?>
                <button class="btn btn-sm btn-light text-danger"><i class="fa fa-times"></i> Ablehnen und löschen</button>
                <?= Html::endForm() ?>
            </div>
        <?php endif ?>

        <div class="row">
            <div class="col-lg-7">
                <p style="white-space:pre-line"><?= Html::encode($mangel->beschreibung) ?></p>
                <p class="text-muted small">
                    <i class="fa fa-map-marker"></i> <?= Html::encode($mangel->getOrt()) ?><br>
                    Gemeldet <?= $fmt->asDatetime($post->content->created_at, 'short') ?> von
                    <?= Html::encode($kontakt?->istGast() ? $kontakt->getName() . ' (ohne Konto)' : ($post->content->createdBy?->displayName ?? '–')) ?>
                    <?= $auch ? " · $auch weitere betroffen" : '' ?>
                </p>
                <?php if ($kontakt): ?>
                    <div class="card card-body mb-3 py-2 small">
                        <strong>Kontakt für Rückfragen</strong>
                        <span><i class="fa fa-user fa-fw"></i> <?= Html::encode($kontakt->getName()) ?></span>
                        <?php if ($mail = $kontakt->getMail()): ?>
                            <span><i class="fa fa-envelope-o fa-fw"></i> <?= Html::mailto(Html::encode($mail), $mail) ?></span>
                        <?php endif ?>
                        <?php if ($kontakt->telefon): ?>
                            <span><i class="fa fa-phone fa-fw"></i> <?= Html::a(Html::encode($kontakt->telefon), 'tel:' . preg_replace('/[^+0-9]/', '', $kontakt->telefon)) ?></span>
                        <?php endif ?>
                        <?php if ($kontakt->info_mail || $kontakt->info_whatsapp): ?>
                            <span><i class="fa fa-bell-o fa-fw"></i> Infos bei Statusänderung: <?= implode(' und ', array_filter([
                                $kontakt->info_mail ? 'E-Mail' : null, $kontakt->info_whatsapp ? 'WhatsApp' : null])) ?></span>
                        <?php endif ?>
                        <span class="text-muted">Nur für den Bauhof sichtbar, wird <?= round(Yii::$app->getModule('maengelmelder')->kontaktAufbewahrungTage / 30) ?> Monate nach Abschluss gelöscht.</span>
                    </div>
                <?php endif ?>
                <?php if ($bilder = $mangel->getBilder()): ?>
                    <div class="bauhof-fotos mb-2">
                        <?php foreach ($bilder as $f): ?>
                            <?php $fotoUrl = $space->createUrl('/maengelmelder/bauhof/foto', ['id' => $id, 'guid' => $f->guid]) ?>
                            <a href="<?= Html::encode($fotoUrl) ?>" target="_blank" rel="noopener"><img src="<?= Html::encode($fotoUrl) ?>" alt="Foto der Meldung" loading="lazy"></a>
                        <?php endforeach ?>
                    </div>
                <?php endif ?>
                <?php if ($mangel->lat): ?>
                    <div id="bauhof-minikarte" style="height:220px;border-radius:6px" class="mb-3"></div>
                <?php endif ?>

                <h3 class="h6 mt-3" id="verlauf">Verlauf<?= $m ? ' mit ' . Html::encode($m->name) : '' ?></h3>
                <div class="bauhof-verlauf" id="bauhof-verlauf"
                     data-url="<?= Html::encode($space->createUrl('/maengelmelder/bauhof/verlauf', ['id' => $id])) ?>">
                    <?= $this->render('_verlauf', ['verlauf' => $verlauf, 'space' => $space, 'mitAuftrag' => false]) ?>
                </div>
                <?php if ($m): ?>
                    <?= Html::beginForm('', 'post', ['class' => 'mt-2', 'id' => 'bauhof-chat']) ?>
                    <?= Html::hiddenInput('aktion', 'chat') ?>
                    <div class="input-group">
                        <?= Html::textarea('text', '', ['class' => 'form-control', 'rows' => 2, 'maxlength' => 2000, 'required' => true,
                            'placeholder' => 'Nachricht an ' . $m->name . ' per WhatsApp …']) ?>
                        <button class="btn btn-primary" title="Senden"><i class="fa fa-paper-plane"></i></button>
                    </div>
                    <small class="text-muted">
                        <?= $m->isFensterOffen()
                            ? 'Kommt direkt an (WhatsApp-Fenster offen bis ' . $fmt->asTime($m->getFensterBis(), 'short') . ').'
                            : Html::encode($m->name) . ' hat seit über 24 Std. nicht geschrieben: Er bekommt zuerst den Hinweis „Neue Nachricht vom Bauhof“, die Nachricht folgt, sobald er darauf tippt.' ?>
                    </small>
                    <?= Html::endForm() ?>
                <?php else: ?>
                    <p class="text-muted small">Zum Schreiben zuerst einen Mitarbeiter zuweisen.</p>
                <?php endif ?>
            </div>

            <div class="col-lg-5">
                <?php if (!$entwurf): ?>
                <?= Html::beginForm('', 'post', ['class' => 'card card-body mb-3']) ?>
                <?= Html::hiddenInput('aktion', 'speichern') ?>
                <?= Html::errorSummary($auftrag, ['class' => 'alert alert-danger']) ?>
                <div class="mb-2">
                    <label class="form-label">Status <small class="text-muted">(öffentlich sichtbar)</small></label>
                    <?= Html::dropDownList('status', $status->status, array_map(fn($s) => $s[0], MaengelStatus::STATUSES), ['class' => 'form-control']) ?>
                    <?= Html::textInput('hinweis_status', '', ['class' => 'form-control form-control-sm mt-1', 'maxlength' => 500,
                        'placeholder' => 'Hinweis für die meldende Person (optional, öffentlich)']) ?>
                </div>
                <hr>
                <div class="mb-2">
                    <?= Html::activeLabel($auftrag, 'mitarbeiter_id', ['class' => 'form-label']) ?>
                    <?= Html::activeDropDownList($auftrag, 'mitarbeiter_id', $mitarbeiter, ['class' => 'form-control', 'prompt' => '– niemand –']) ?>
                    <div class="form-check mt-1">
                        <?= Html::checkbox('senden', true, ['class' => 'form-check-input', 'id' => 'bauhof-senden']) ?>
                        <label class="form-check-label small" for="bauhof-senden">Bei neuer Zuweisung per WhatsApp schicken</label>
                    </div>
                    <?php if ($m): ?>
                        <small class="text-muted">
                            Zugewiesen <?= $auftrag->zugewiesen_at ? $fmt->asDatetime($auftrag->zugewiesen_at, 'short') : '' ?>
                            <?= $auftrag->zuweiser ? 'von ' . Html::encode($auftrag->zuweiser->displayName) : '' ?>
                            · <?= $auftrag->gesendet_at ? 'per WhatsApp gesendet ' . $fmt->asDatetime($auftrag->gesendet_at, 'short') : 'noch nicht gesendet' ?>
                        </small>
                    <?php endif ?>
                </div>
                <div class="row g-2 mb-2">
                    <div class="col-6">
                        <?= Html::activeLabel($auftrag, 'prioritaet', ['class' => 'form-label']) ?>
                        <?= Html::activeDropDownList($auftrag, 'prioritaet', array_map(fn($p) => $p[0], Auftrag::PRIORITAETEN), ['class' => 'form-control']) ?>
                    </div>
                    <div class="col-6">
                        <?= Html::activeLabel($auftrag, 'faellig', ['class' => 'form-label']) ?>
                        <?= Html::activeInput('date', $auftrag, 'faellig', ['class' => 'form-control']) ?>
                    </div>
                </div>
                <div class="mb-2">
                    <?= Html::activeLabel($auftrag, 'hinweis', ['class' => 'form-label']) ?>
                    <?= Html::activeTextarea($auftrag, 'hinweis', ['class' => 'form-control', 'rows' => 2, 'placeholder' => 'z. B. Kaltmischgut mitnehmen']) ?>
                </div>
                <div class="mb-3">
                    <?= Html::activeLabel($auftrag, 'notiz', ['class' => 'form-label']) ?>
                    <?= Html::activeTextarea($auftrag, 'notiz', ['class' => 'form-control', 'rows' => 2]) ?>
                </div>
                <button class="btn btn-primary"><i class="fa fa-save"></i> Speichern</button>
                <?= Html::endForm() ?>

                <?php if ($m): ?>
                    <?= Html::beginForm('', 'post') ?>
                    <?= Html::hiddenInput('aktion', 'senden') ?>
                    <button class="btn btn-sm btn-light"><i class="fa fa-whatsapp"></i> Auftrag erneut an <?= Html::encode($m->name) ?> schicken</button>
                    <?= Html::a('<i class="fa fa-comments"></i> Alle Nachrichten', $space->createUrl('/maengelmelder/bauhof/chat', ['id' => $m->id]), ['class' => 'btn btn-sm btn-light']) ?>
                    <?= Html::endForm() ?>
                <?php endif ?>
                <?php endif ?>
            </div>
        </div>
    </div>
</div>
<?= $this->render('../meldung/_tiles') ?>
<?= $this->render('_verlaufJs') ?>
<script <?= Html::nonce() ?>>
$('#bauhof-ablehnen').on('submit', function (e) {
    if (!confirm('Gastmeldung Nr. <?= $id ?> ablehnen? Sie wird samt Fotos und Kontaktdaten endgültig gelöscht.')) e.preventDefault();
});
$('#bauhof-loeschen').on('submit', function (e) {
    if (!confirm('Meldung Nr. <?= $id ?> wirklich löschen? Sie verschwindet auch für die meldende Person.')) e.preventDefault();
});
<?php if ($mangel->lat): ?>
maengelReady(function () {
    var el = document.getElementById('bauhof-minikarte');
    if (!el || el._leaflet_id) return;
    var map = L.map(el, {scrollWheelZoom: false}).setView([<?= $mangel->lat ?>, <?= $mangel->lng ?>], 17);
    maengelTiles(map);
    L.circleMarker([<?= $mangel->lat ?>, <?= $mangel->lng ?>], {radius: 10, color: '#fff', weight: 2, fillColor: <?= json_encode($status->getColor()) ?>, fillOpacity: .95}).addTo(map);
});
<?php endif ?>
</script>
