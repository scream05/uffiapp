<?php
/**
 * @var \humhub\modules\kurse\models\Kurs $kurs
 * @var bool $verwalten
 */

use humhub\helpers\Html;
use humhub\modules\kurse\models\Anmeldung;
use humhub\modules\kurse\models\Kurs;

$space = $this->context->contentContainer;
[$label, $farbe] = $kurs->getStatusLabel();
$gesperrt = $kurs->anmeldungGesperrt();
$text = fn(?string $t) => nl2br(Html::encode((string)$t), false);
$post = fn(string $label, string $route, string $class, ?string $confirm = null) => Html::a($label, $space->createUrl($route, ['id' => $kurs->id]),
    ['class' => "btn btn-sm $class", 'data-method' => 'post'] + ($confirm ? ['data-confirm' => $confirm] : []));
echo $this->render('_stil');
?>
<div class="panel panel-default">
    <div class="panel-body">
        <p><?= Html::a('<i class="fa fa-arrow-left"></i> Alle Kurse', $space->createUrl('/kurse/kurs/index')) ?></p>
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
            <div>
                <h1 style="margin:0 0 4px;font-size:1.5em"><?= Html::encode($kurs->titel) ?></h1>
                <span class="text-muted"><?= Html::a(Html::encode($kurs->verein->name), $kurs->verein->getUrl()) ?><?= $kurs->niveau ? ' · ' . Html::encode($kurs->niveau) : '' ?></span>
            </div>
            <span class="badge text-bg-<?= $farbe ?>" style="font-size:.9em"><?= Html::encode($label) ?></span>
        </div>

        <?php if ($verwalten): ?>
            <?php $platz = $kurs->zaehle(Anmeldung::STATUS_PLATZ); $wl = $kurs->zaehle(Anmeldung::STATUS_WARTELISTE); ?>
            <div class="alert alert-light border mt-3 mb-0">
                <strong><?= $platz ?>/<?= $kurs->plaetze ?> belegt</strong> · <?= $wl ?> auf der Warteliste
                <div class="kurs-belegung"><div style="width:<?= min(100, round(100 * $platz / max(1, $kurs->plaetze))) ?>%"></div></div>
                <div class="d-flex flex-wrap gap-1 mt-2">
                    <?= Html::a('<i class="fa fa-users"></i> Teilnehmer', $space->createUrl('/kurse/kurs/teilnehmer', ['id' => $kurs->id]), ['class' => 'btn btn-primary btn-sm']) ?>
                    <?php if ($kurs->status === Kurs::STATUS_ENTWURF): ?>
                        <?= $post('<i class="fa fa-eye"></i> Veröffentlichen', '/kurse/kurs/veroeffentlichen', 'btn-success', 'Kurs veröffentlichen? Danach ist er sichtbar und – je nach Anmeldezeitraum – buchbar.') ?>
                    <?php endif ?>
                    <?= Html::a('<i class="fa fa-pencil"></i> Bearbeiten', $space->createUrl('/kurse/kurs/edit', ['id' => $kurs->id]), ['class' => 'btn btn-light btn-sm']) ?>
                    <?= $post('<i class="fa fa-copy"></i> Kopieren', '/kurse/kurs/kopieren', 'btn-light') ?>
                    <?php if ($kurs->status === Kurs::STATUS_OFFEN): ?>
                        <?= Html::a('<i class="fa fa-ban"></i> Absagen', $space->createUrl('/kurse/kurs/absagen', ['id' => $kurs->id]), ['class' => 'btn btn-light btn-sm']) ?>
                    <?php elseif ($kurs->status === Kurs::STATUS_ENTWURF): ?>
                        <?= $post('<i class="fa fa-trash"></i> Löschen', '/kurse/kurs/loeschen', 'btn-danger', 'Diesen Kurs wirklich löschen?') ?>
                    <?php endif ?>
                </div>
            </div>
        <?php endif ?>

        <?php if ($kurs->status === Kurs::STATUS_ABGESAGT): ?>
            <div class="alert alert-danger mt-3">Dieser Kurs wurde abgesagt.<?= $kurs->absage_grund ? '<br>' . $text($kurs->absage_grund) : '' ?></div>
        <?php endif ?>

        <dl class="kurs-fakten">
            <dt>Termine</dt><dd><?= Html::encode($kurs->getTermineText()) ?><?= $kurs->termine_hinweis ? '<br><span class="text-muted">' . Html::encode($kurs->termine_hinweis) . '</span>' : '' ?></dd>
            <dt>Ort</dt><dd><?= Html::encode($kurs->ort) ?></dd>
            <?php if ($alter = $kurs->getAlterText()): ?><dt>Alter</dt><dd><?= Html::encode($alter) ?> (am ersten Kurstag)</dd><?php endif ?>
            <dt>Kosten</dt><dd><?= Html::encode($kurs->getPreisText()) ?><?php if ($kurs->hatPreis()): ?><br><span class="text-muted">Zahlung: <?= Html::encode(implode(' oder ', $kurs->zahlarten())) ?></span><?php endif ?></dd>
            <dt>Plätze</dt><dd><?= $kurs->plaetze ?><?= $kurs->nur_mitglieder ? ' · nur für Vereinsmitglieder' : '' ?></dd>
            <?php if ($kurs->anmeldung_ab || $kurs->anmeldung_bis): ?>
                <dt>Anmeldung</dt><dd><?= $kurs->anmeldung_ab ? 'ab ' . date('d.m.Y, H:i', strtotime($kurs->anmeldung_ab)) . ' Uhr' : '' ?>
                    <?= $kurs->anmeldung_bis ? ' bis ' . date('d.m.Y, H:i', strtotime($kurs->anmeldung_bis)) . ' Uhr' : '' ?></dd>
            <?php endif ?>
            <dt>Kontakt</dt><dd><?= Html::mailto(Html::encode($kurs->kontakt_email), $kurs->kontakt_email) ?></dd>
        </dl>

        <?php if ($kurs->beschreibung): ?>
            <p><?= $text($kurs->beschreibung) ?></p>
        <?php endif ?>

        <?php if ($gesperrt === null): ?>
            <?= Html::a(($kurs->freiePlaetze() > 0 ? 'Jetzt anmelden' : 'Auf die Warteliste setzen'), $space->createUrl('/kurse/anmeldung/create', ['kurs' => $kurs->id]), ['class' => 'btn btn-primary']) ?>
            <p class="help-block mt-2">Plätze werden in der Reihenfolge vergeben, in der die Anmeldungen per E-Mail bestätigt werden.</p>
        <?php elseif ($kurs->status !== Kurs::STATUS_ENTWURF && $kurs->status !== Kurs::STATUS_ABGESAGT): ?>
            <div class="alert alert-info"><?= Html::encode($gesperrt) ?></div>
        <?php endif ?>

        <?php if ($kurs->bedingungen): ?>
            <details class="mt-3"><summary><strong>Teilnahmebedingungen</strong></summary><p class="mt-2"><?= $text($kurs->bedingungen) ?></p></details>
        <?php endif ?>
        <p class="mt-3 small"><?= Html::a('Datenschutzhinweise zur Kursanmeldung', $space->createUrl('/kurse/kurs/datenschutz', ['id' => $kurs->id])) ?></p>
    </div>
</div>
