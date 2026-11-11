<?php
/**
 * @var \humhub\modules\kurse\models\Kurs $kurs
 * @var string $liste
 * @var array $listen
 * @var array $zahlen
 * @var \humhub\modules\kurse\models\Anmeldung[] $anmeldungen
 * @var float $offenBetrag
 */

use humhub\helpers\Html;
use humhub\modules\kurse\models\Anmeldung;
use humhub\modules\kurse\models\Kurs;

$space = $this->context->contentContainer;
$knopf = function (Anmeldung $a, string $aktion, string $label, string $class, ?string $confirm = null, array $felder = []) use ($space) {
    $h = Html::beginForm($space->createUrl('/kurse/anmeldung/' . $aktion, ['id' => $a->id]), 'post');
    foreach ($felder as $k => $v) {
        $h .= Html::hiddenInput($k, $v);
    }
    return $h . Html::submitButton($label, ['class' => "btn btn-sm $class"] + ($confirm ? ['data-confirm' => $confirm] : [])) . Html::endForm();
};
echo $this->render('_stil');
?>
<div class="panel panel-default">
    <div class="panel-heading"><strong>Teilnehmer: <?= Html::encode($kurs->titel) ?></strong></div>
    <div class="panel-body">
        <p><?= Html::a('<i class="fa fa-arrow-left"></i> Zum Kurs', $kurs->getUrl()) ?></p>
        <p class="mb-2"><strong><?= $zahlen['platz'] ?>/<?= $kurs->plaetze ?> belegt</strong> · <?= $zahlen['warteliste'] ?> auf der Warteliste
            <?php if ($offenBetrag > 0): ?> · <span class="text-danger">offen: <?= Kurs::euro($offenBetrag) ?></span><?php endif ?></p>
        <div class="d-flex flex-wrap gap-1 mb-3">
            <?= Html::a('<i class="fa fa-user-plus"></i> Von Hand eintragen', $space->createUrl('/kurse/anmeldung/hand', ['kurs' => $kurs->id]), ['class' => 'btn btn-light btn-sm']) ?>
            <?= Html::a('<i class="fa fa-envelope"></i> Rundmail', $space->createUrl('/kurse/kurs/rundmail', ['id' => $kurs->id]), ['class' => 'btn btn-light btn-sm']) ?>
            <?= Html::a('<i class="fa fa-download"></i> Liste (Excel/CSV)', $space->createUrl('/kurse/kurs/export', ['id' => $kurs->id]), ['class' => 'btn btn-light btn-sm', 'data-pjax-prevent' => '', 'download' => '']) ?>
        </div>

        <ul class="nav nav-tabs mb-2">
            <?php foreach ($listen as $key => $label): ?>
                <li class="nav-item"><?= Html::a($label . ' (' . $zahlen[$key] . ')', $space->createUrl('/kurse/kurs/teilnehmer', ['id' => $kurs->id, 'liste' => $key]),
                        ['class' => 'nav-link' . ($key === $liste ? ' active' : '')]) ?></li>
            <?php endforeach ?>
        </ul>
        <?php if ($liste === 'unbestaetigt'): ?>
            <p class="help-block">Diese Anmeldungen warten auf die Bestätigung per E-Mail und werden nach 48 Stunden automatisch gelöscht.</p>
        <?php elseif ($liste === 'warteliste'): ?>
            <p class="help-block">Wird vor Kursbeginn ein Platz frei, rückt automatisch das erste Kind der Liste nach (mit Mail). Mit „Platz geben“ nimmst du jemanden direkt auf – auch über die Platzzahl hinaus.</p>
        <?php endif ?>

        <?php if (!$anmeldungen): ?>
            <p class="text-muted">Niemand in dieser Liste.</p>
        <?php endif ?>
        <?php foreach ($anmeldungen as $i => $a): ?>
            <div class="kurs-zeile" id="a<?= $a->id ?>">
                <div class="d-flex justify-content-between flex-wrap gap-2">
                    <div style="min-width:200px;flex:1">
                        <strong><?= $liste === 'warteliste' ? ($i + 1) . '. ' : '' ?><?= Html::encode($a->getKindName()) ?></strong>
                        <span class="text-muted small"><?= Html::encode($a->getAlterText()) ?> · <?= $a->getNummer() ?><?= $a->mitglied ? ' · Mitglied' : '' ?><?= $a->von_hand ? ' · von Hand' : '' ?></span>
                        <div class="small">
                            <?= Html::encode($a->eltern_name) ?>
                            <?php if ($a->telefon): ?> · <?= Html::a(Html::encode($a->telefon), 'tel:' . preg_replace('/[^0-9+]/', '', $a->telefon)) ?><?php endif ?>
                            <?php if ($a->email): ?> · <?= Html::mailto(Html::encode($a->email), $a->email) ?><?php endif ?>
                            <?php if ($a->notfall_telefon): ?><br>Notfall: <?= Html::encode($a->notfall_telefon) ?><?php endif ?>
                            <?php if ($a->schwimmstand): ?><br><span class="text-muted">Kann schon: <?= Html::encode($a->schwimmstand) ?></span><?php endif ?>
                            <?php if ($a->notiz): ?><br><i class="fa fa-sticky-note-o"></i> <?= Html::encode($a->notiz) ?><?php endif ?>
                        </div>
                    </div>
                    <div class="text-end small" style="min-width:150px">
                        <?php if ($a->betrag !== null && (float)$a->betrag > 0): ?>
                            <?= Kurs::euro($a->betrag) ?> · <?= $a->zahlart === Anmeldung::ZAHLART_BAR ? 'bar' : 'Überweisung' ?><br>
                            <?php if ($a->bezahlt_am): ?>
                                <span class="badge text-bg-success">bezahlt <?= date('d.m.', strtotime($a->bezahlt_am)) ?></span>
                                <?= $knopf($a, 'bezahlt', 'zurücknehmen', 'btn-link p-0', 'Zahlung wirklich als offen markieren?') ?>
                            <?php else: ?>
                                <span class="badge text-bg-warning">offen</span>
                                <?php if ($a->status === Anmeldung::STATUS_PLATZ): ?><?= $knopf($a, 'bezahlt', '<i class="fa fa-check"></i> bezahlt', 'btn-light') ?><?php endif ?>
                            <?php endif ?>
                        <?php elseif ($a->status === Anmeldung::STATUS_STORNIERT): ?>
                            <span class="text-muted"><?= Html::encode(['eltern' => 'von Eltern abgemeldet', 'verein' => 'vom Verein abgemeldet', 'absage' => 'Kurs abgesagt', 'voll' => 'kein Platz mehr frei', 'doppelt' => 'doppelte Anmeldung'][$a->storniert_von] ?? '') ?>
                                <?= $a->storniert_am ? date('d.m.', strtotime($a->storniert_am)) : '' ?></span>
                        <?php endif ?>
                    </div>
                </div>
                <div class="d-flex flex-wrap gap-1 mt-1">
                    <?php if ($a->status === Anmeldung::STATUS_WARTELISTE || $a->status === Anmeldung::STATUS_UNBESTAETIGT): ?>
                        <?= $knopf($a, 'platz', 'Platz geben', 'btn-primary', 'Platz geben' . ($a->email ? ' und per Mail informieren?' : '?')) ?>
                    <?php elseif ($a->status === Anmeldung::STATUS_STORNIERT && $kurs->status !== Kurs::STATUS_ABGESAGT): ?>
                        <?= $knopf($a, 'platz', 'Wieder aufnehmen', 'btn-light', 'Wieder mit Platz aufnehmen' . ($a->email ? ' und per Mail informieren?' : '?')) ?>
                    <?php endif ?>
                    <?php if ($a->status !== Anmeldung::STATUS_STORNIERT): ?>
                        <?= $knopf($a, 'abmelden', 'Abmelden', 'btn-light', 'Wirklich abmelden?' . ($a->status === Anmeldung::STATUS_PLATZ ? ' Der Platz geht an die Warteliste.' : '') . ($a->email && $a->bestaetigt_am ? ' Die Eltern bekommen eine Mail.' : '')) ?>
                    <?php endif ?>
                    <details class="d-inline-block"><summary class="btn btn-sm btn-light">Notiz</summary>
                        <?= Html::beginForm($space->createUrl('/kurse/anmeldung/notiz', ['id' => $a->id]), 'post', ['class' => 'mt-1 d-flex gap-1']) ?>
                        <?= Html::textInput('notiz', $a->notiz, ['class' => 'form-control form-control-sm', 'maxlength' => 1000, 'placeholder' => 'nur für Kursverwalter sichtbar']) ?>
                        <?= Html::submitButton('Speichern', ['class' => 'btn btn-sm btn-primary']) ?>
                        <?= Html::endForm() ?>
                    </details>
                </div>
            </div>
        <?php endforeach ?>
    </div>
</div>
