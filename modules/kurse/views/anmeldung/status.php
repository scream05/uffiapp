<?php
/** @var \humhub\modules\kurse\models\Anmeldung $a */

use humhub\helpers\Html;
use humhub\modules\kurse\models\Anmeldung;
use humhub\modules\kurse\models\Kurs;

$kurs = $a->kurs;
$farbe = [Anmeldung::STATUS_PLATZ => 'success', Anmeldung::STATUS_WARTELISTE => 'warning', Anmeldung::STATUS_STORNIERT => 'secondary'][$a->status] ?? 'info';
?>
<div class="panel panel-default">
    <div class="panel-body">
        <h1 style="font-size:1.4em">Anmeldung <?= $a->getNummer() ?></h1>
        <p class="mb-1"><strong><?= Html::encode($a->getKindName()) ?></strong> · <?= Html::encode($kurs->titel) ?> (<?= Html::encode($kurs->verein->name) ?>)</p>
        <p><span class="badge text-bg-<?= $farbe ?>" style="font-size:1em"><?= Html::encode($a->getStatusText()) ?><?=
            $a->status === Anmeldung::STATUS_WARTELISTE ? ' – Platz ' . $a->getWartelistenPosition() : '' ?></span></p>

        <?php if ($kurs->status === Kurs::STATUS_ABGESAGT): ?>
            <div class="alert alert-danger">Der Kurs wurde abgesagt.<?= $kurs->absage_grund ? '<br>' . nl2br(Html::encode($kurs->absage_grund), false) : '' ?></div>
        <?php elseif ($a->status === Anmeldung::STATUS_WARTELISTE): ?>
            <p>Wird ein Platz frei, rückt dein Kind automatisch nach und du bekommst eine Mail.</p>
        <?php elseif ($a->status === Anmeldung::STATUS_STORNIERT && $a->storniert_von === 'voll'): ?>
            <p>Kurs und Warteliste waren bei deiner Bestätigung leider schon voll.</p>
        <?php elseif ($a->status === Anmeldung::STATUS_STORNIERT && $a->storniert_von === 'doppelt'): ?>
            <p>Für dieses Kind gab es bei deiner Bestätigung schon eine andere bestätigte Anmeldung zu diesem Kurs. Diese hier zählt deshalb nicht.</p>
        <?php endif ?>

        <dl class="kurs-fakten" style="display:grid;grid-template-columns:max-content 1fr;gap:4px 14px">
            <dt>Termine</dt><dd class="m-0"><?= Html::encode($kurs->getTermineText()) ?><?= $kurs->termine_hinweis ? '<br>' . Html::encode($kurs->termine_hinweis) : '' ?></dd>
            <dt>Ort</dt><dd class="m-0"><?= Html::encode($kurs->ort) ?></dd>
            <?php if ($a->status === Anmeldung::STATUS_PLATZ && $a->betrag !== null && (float)$a->betrag > 0): ?>
                <dt>Kursgebühr</dt><dd class="m-0"><?= Kurs::euro($a->betrag) ?> ·
                    <?php if ($a->bezahlt_am): ?><span class="text-success">bezahlt</span>
                    <?php elseif ($a->zahlart === Anmeldung::ZAHLART_BAR): ?>bar am ersten Kurstag
                    <?php else: ?>per Überweisung<?= ($f = $a->zahlungsfrist()) ? ' bis ' . date('d.m.Y', strtotime($f)) : '' ?><br>
                        <?= Html::encode($kurs->kontoinhaber) ?> · IBAN <?= Html::encode(trim(chunk_split((string)$kurs->iban, 4, ' '))) ?><br>
                        Verwendungszweck: <strong><?= Html::encode($a->getVerwendungszweck()) ?></strong>
                    <?php endif ?></dd>
            <?php endif ?>
            <dt>Eltern</dt><dd class="m-0"><?= Html::encode($a->eltern_name) ?><?= $a->email ? ' · ' . Html::encode($a->email) : '' ?></dd>
            <dt>Kontakt</dt><dd class="m-0"><?= Html::mailto(Html::encode($kurs->kontakt_email), $kurs->kontakt_email) ?></dd>
        </dl>

        <?php if (in_array($a->status, [Anmeldung::STATUS_PLATZ, Anmeldung::STATUS_WARTELISTE], true) && !$kurs->istVorbei()): ?>
            <?= Html::beginForm() ?>
            <?= Html::hiddenInput('aktion', 'abmelden') ?>
            <?= Html::submitButton('Vom Kurs abmelden', ['class' => 'btn btn-light', 'data-confirm' => 'Wirklich abmelden? Der Platz geht an das nächste Kind auf der Warteliste.']) ?>
            <?= Html::endForm() ?>
        <?php endif ?>
        <p class="small text-muted mt-3">Diese Seite ist nur über den Link in deiner Mail erreichbar. Bitte gib ihn nicht weiter.
            <?= Html::a('Datenschutzhinweise', $kurs->createUrl('/kurse/kurs/datenschutz', ['id' => $kurs->id])) ?></p>
    </div>
</div>
