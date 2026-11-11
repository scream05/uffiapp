<?php
/**
 * Persönliche Seite einer Anmeldung (nur über den Link aus der Mail)
 * @var \humhub\modules\kurse\models\Anmeldung $a
 */

use humhub\helpers\Html;
use humhub\modules\kurse\models\Anmeldung;
use humhub\modules\kurse\models\Kurs;

$kurs = $a->kurs;
$farbe = [Anmeldung::STATUS_PLATZ => 'success', Anmeldung::STATUS_WARTELISTE => 'warning', Anmeldung::STATUS_STORNIERT => 'secondary'][$a->status] ?? 'info';
?>
<div class="breite schmal abschnitt">
    <div class="karte">
        <p class="grau klein" style="margin:0">Anmeldung <?= Html::encode($a->getNummer()) ?></p>
        <h1 style="margin:0 0 6px;font-size:1.6em"><?= Html::encode($a->getKindName()) ?></h1>
        <p style="margin:0 0 10px"><?= Html::a(Html::encode($kurs->titel), $kurs->elternUrl('kurs')) ?></p>
        <p><span class="marke-status s-<?= $farbe ?>" style="font-size:1em"><?= Html::encode($a->getStatusText()) ?><?=
            $a->status === Anmeldung::STATUS_WARTELISTE ? ' – Platz ' . $a->getWartelistenPosition() : '' ?></span></p>

        <?php if ($kurs->status === Kurs::STATUS_ABGESAGT): ?>
            <div class="meldung fehler">Der Kurs wurde abgesagt.<?= $kurs->absage_grund ? '<br>' . nl2br(Html::encode($kurs->absage_grund), false) : '' ?></div>
        <?php elseif ($a->status === Anmeldung::STATUS_WARTELISTE): ?>
            <p>Wird ein Platz frei, rückt dein Kind automatisch nach und du bekommst eine Mail.</p>
        <?php elseif ($a->status === Anmeldung::STATUS_STORNIERT && $a->storniert_von === 'voll'): ?>
            <p>Kurs und Warteliste waren bei deiner Bestätigung leider schon voll.</p>
        <?php elseif ($a->status === Anmeldung::STATUS_STORNIERT && $a->storniert_von === 'doppelt'): ?>
            <p>Für dieses Kind gab es bei deiner Bestätigung schon eine andere bestätigte Anmeldung zu diesem Kurs. Diese hier zählt deshalb nicht.</p>
        <?php endif ?>

        <dl class="fakten">
            <dt>Termine</dt><dd><?= Html::encode($kurs->getTermineText()) ?><?= $kurs->termine_hinweis ? '<br>' . Html::encode($kurs->termine_hinweis) : '' ?></dd>
            <dt>Ort</dt><dd><?= Html::encode($kurs->ort) ?></dd>
            <?php if ($a->status === Anmeldung::STATUS_PLATZ && $a->betrag !== null && (float)$a->betrag > 0): ?>
                <dt>Kursgebühr</dt><dd><?= Kurs::euro($a->betrag) ?> ·
                    <?php if ($a->bezahlt_am): ?><strong style="color:var(--ok)">bezahlt</strong>
                    <?php elseif ($a->zahlart === Anmeldung::ZAHLART_BAR): ?>bar am ersten Kurstag
                    <?php else: ?>per Überweisung<?= ($f = $a->zahlungsfrist()) ? ' bis ' . date('d.m.Y', strtotime($f)) : '' ?><br>
                        <?= Html::encode($kurs->kontoinhaber) ?> · IBAN <?= Html::encode(trim(chunk_split((string)$kurs->iban, 4, ' '))) ?><br>
                        Verwendungszweck: <strong><?= Html::encode($a->getVerwendungszweck()) ?></strong>
                    <?php endif ?></dd>
            <?php endif ?>
            <dt>Eltern</dt><dd><?= Html::encode($a->eltern_name) ?><?= $a->email ? ' · ' . Html::encode($a->email) : '' ?></dd>
            <dt>Kontakt</dt><dd><?= Html::mailto(Html::encode($kurs->kontakt_email), $kurs->kontakt_email) ?></dd>
        </dl>

        <?php if (in_array($a->status, [Anmeldung::STATUS_PLATZ, Anmeldung::STATUS_WARTELISTE], true) && !$kurs->istVorbei()): ?>
            <?= Html::beginForm('', 'post', ['id' => 'abmelden']) ?>
            <?= Html::hiddenInput('aktion', 'abmelden') ?>
            <button type="submit" class="knopf rand">Vom Kurs abmelden</button>
            <?= Html::endForm() ?>
            <script <?= Html::nonce() ?>>
                document.getElementById('abmelden').addEventListener('submit', function (e) {
                    if (!confirm('Wirklich abmelden? Der Platz geht an das nächste Kind auf der Warteliste.')) { e.preventDefault(); }
                });
            </script>
        <?php endif ?>
        <p class="grau klein" style="margin-top:20px">Diese Seite ist nur über den Link in deiner Mail erreichbar. Bitte gib ihn nicht weiter.
            <a href="<?= Html::encode($kurs->elternUrl('datenschutz')) ?>">Datenschutzhinweise</a></p>
    </div>
</div>
