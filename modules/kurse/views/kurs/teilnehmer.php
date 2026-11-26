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
    <div class="panel-heading"><strong><?= Yii::t('KurseModule.base', 'Teilnehmer: {kurs}', ['kurs' => Html::encode($kurs->titel)]) ?></strong></div>
    <div class="panel-body">
        <p><?= Html::a('<i class="fa fa-arrow-left"></i> ' . Html::encode(Yii::t('KurseModule.base', 'Zum Kurs')), $kurs->getUrl()) ?></p>
        <p class="mb-2"><strong><?= Yii::t('KurseModule.base', '{belegt}/{plaetze} belegt', ['belegt' => $zahlen['platz'], 'plaetze' => $kurs->plaetze]) ?></strong> · <?= Yii::t('KurseModule.base', '{n} auf der Warteliste', ['n' => $zahlen['warteliste']]) ?>
            <?php if ($offenBetrag > 0): ?> · <span class="text-danger"><?= Yii::t('KurseModule.base', 'offen: {betrag}', ['betrag' => Kurs::euro($offenBetrag)]) ?></span><?php endif ?></p>
        <div class="d-flex flex-wrap gap-1 mb-3">
            <?= Html::a('<i class="fa fa-user-plus"></i> ' . Html::encode(Yii::t('KurseModule.base', 'Von Hand eintragen')), $space->createUrl('/kurse/anmeldung/hand', ['kurs' => $kurs->id]), ['class' => 'btn btn-light btn-sm']) ?>
            <?= Html::a('<i class="fa fa-envelope"></i> ' . Html::encode(Yii::t('KurseModule.base', 'Rundmail')), $space->createUrl('/kurse/kurs/rundmail', ['id' => $kurs->id]), ['class' => 'btn btn-light btn-sm']) ?>
            <?= Html::a('<i class="fa fa-download"></i> ' . Html::encode(Yii::t('KurseModule.base', 'Liste (Excel/CSV)')), $space->createUrl('/kurse/kurs/export', ['id' => $kurs->id]), ['class' => 'btn btn-light btn-sm', 'data-pjax-prevent' => '', 'download' => '']) ?>
        </div>

        <ul class="nav nav-tabs mb-2">
            <?php foreach ($listen as $key => $label): ?>
                <li class="nav-item"><?= Html::a(Html::encode($label) . ' (' . $zahlen[$key] . ')', $space->createUrl('/kurse/kurs/teilnehmer', ['id' => $kurs->id, 'liste' => $key]),
                        ['class' => 'nav-link' . ($key === $liste ? ' active' : '')]) ?></li>
            <?php endforeach ?>
        </ul>
        <?php if ($liste === 'unbestaetigt'): ?>
            <p class="help-block"><?= Yii::t('KurseModule.base', 'Diese Anmeldungen warten auf die Bestätigung per E-Mail und werden nach {stunden} Stunden automatisch gelöscht.', ['stunden' => Yii::$app->getModule('kurse')->unbestaetigtStunden]) ?></p>
        <?php elseif ($liste === 'warteliste'): ?>
            <p class="help-block"><?= Yii::t('KurseModule.base', 'Wird vor Kursbeginn ein Platz frei, rückt automatisch das erste Kind der Liste nach (mit Mail). Mit {knopf} nimmst du jemanden direkt auf – auch über die Platzzahl hinaus.', ['knopf' => '„' . Html::encode(Yii::t('KurseModule.base', 'Platz geben')) . '“']) ?></p>
        <?php endif ?>

        <?php if (!$anmeldungen): ?>
            <p class="text-muted"><?= Yii::t('KurseModule.base', 'Niemand in dieser Liste.') ?></p>
        <?php endif ?>
        <?php foreach ($anmeldungen as $i => $a): ?>
            <div class="kurs-zeile" id="a<?= $a->id ?>">
                <div class="d-flex justify-content-between flex-wrap gap-2">
                    <div style="min-width:200px;flex:1">
                        <strong><?= $liste === 'warteliste' ? ($i + 1) . '. ' : '' ?><?= Html::encode($a->getKindName()) ?></strong>
                        <span class="text-muted small"><?= Html::encode($a->getAlterText()) ?> · <?= $a->getNummer() ?><?= $a->mitglied ? ' · ' . Yii::t('KurseModule.base', 'Mitglied') : '' ?><?= $a->von_hand ? ' · ' . Yii::t('KurseModule.base', 'von Hand') : '' ?></span>
                        <div class="small">
                            <?= Html::encode($a->eltern_name) ?>
                            <?php if ($a->telefon): ?> · <?= Html::a(Html::encode($a->telefon), 'tel:' . preg_replace('/[^0-9+]/', '', $a->telefon)) ?><?php endif ?>
                            <?php if ($a->email): ?> · <?= Html::mailto(Html::encode($a->email), $a->email) ?><?php endif ?>
                            <?php if ($a->notfall_telefon): ?><br><?= Yii::t('KurseModule.base', 'Notfall: {telefon}', ['telefon' => Html::encode($a->notfall_telefon)]) ?><?php endif ?>
                            <?php if ($a->schwimmstand): ?><br><span class="text-muted"><?= Yii::t('KurseModule.base', 'Kann schon: {text}', ['text' => Html::encode($a->schwimmstand)]) ?></span><?php endif ?>
                            <?php if ($a->notiz): ?><br><i class="fa fa-sticky-note-o"></i> <?= Html::encode($a->notiz) ?><?php endif ?>
                        </div>
                    </div>
                    <div class="text-end small" style="min-width:150px">
                        <?php if ($a->betrag !== null && (float)$a->betrag > 0): ?>
                            <?= Kurs::euro($a->betrag) ?> · <?= $a->zahlart === Anmeldung::ZAHLART_BAR ? Yii::t('KurseModule.base', 'bar') : Yii::t('KurseModule.base', 'Überweisung') ?><br>
                            <?php if ($a->bezahlt_am): ?>
                                <span class="badge text-bg-success"><?= Yii::t('KurseModule.base', 'bezahlt {datum}', ['datum' => Kurs::datum($a->bezahlt_am)]) ?></span>
                                <?= $knopf($a, 'bezahlt', Html::encode(Yii::t('KurseModule.base', 'zurücknehmen')), 'btn-link p-0', Yii::t('KurseModule.base', 'Zahlung wirklich als offen markieren?')) ?>
                            <?php else: ?>
                                <span class="badge text-bg-warning"><?= Yii::t('KurseModule.base', 'offen') ?></span>
                                <?php if ($a->status === Anmeldung::STATUS_PLATZ): ?><?= $knopf($a, 'bezahlt', '<i class="fa fa-check"></i> ' . Html::encode(Yii::t('KurseModule.base', 'bezahlt')), 'btn-light') ?><?php endif ?>
                            <?php endif ?>
                        <?php elseif ($a->status === Anmeldung::STATUS_STORNIERT): ?>
                            <span class="text-muted"><?= Html::encode(['eltern' => Yii::t('KurseModule.base', 'von Eltern abgemeldet'), 'verein' => Yii::t('KurseModule.base', 'vom Verein abgemeldet'),
                                    'absage' => Yii::t('KurseModule.base', 'Kurs abgesagt'), 'voll' => Yii::t('KurseModule.base', 'kein Platz mehr frei'), 'doppelt' => Yii::t('KurseModule.base', 'doppelte Anmeldung')][$a->storniert_von] ?? '') ?>
                                <?= $a->storniert_am ? Html::encode(Kurs::datum($a->storniert_am)) : '' ?></span>
                        <?php endif ?>
                    </div>
                </div>
                <div class="d-flex flex-wrap gap-1 mt-1">
                    <?php if ($a->status === Anmeldung::STATUS_WARTELISTE || $a->status === Anmeldung::STATUS_UNBESTAETIGT): ?>
                        <?= $knopf($a, 'platz', Html::encode(Yii::t('KurseModule.base', 'Platz geben')), 'btn-primary', $a->email ? Yii::t('KurseModule.base', 'Platz geben und per Mail informieren?') : Yii::t('KurseModule.base', 'Platz geben?')) ?>
                    <?php elseif ($a->status === Anmeldung::STATUS_STORNIERT && $kurs->status !== Kurs::STATUS_ABGESAGT): ?>
                        <?= $knopf($a, 'platz', Html::encode(Yii::t('KurseModule.base', 'Wieder aufnehmen')), 'btn-light', $a->email ? Yii::t('KurseModule.base', 'Wieder mit Platz aufnehmen und per Mail informieren?') : Yii::t('KurseModule.base', 'Wieder mit Platz aufnehmen?')) ?>
                    <?php endif ?>
                    <?php if ($a->status !== Anmeldung::STATUS_STORNIERT): ?>
                        <?= $knopf($a, 'abmelden', Html::encode(Yii::t('KurseModule.base', 'Abmelden')), 'btn-light', Yii::t('KurseModule.base', 'Wirklich abmelden?')
                            . ($a->status === Anmeldung::STATUS_PLATZ ? ' ' . Yii::t('KurseModule.base', 'Der Platz geht an die Warteliste.') : '')
                            . ($a->email && $a->bestaetigt_am ? ' ' . Yii::t('KurseModule.base', 'Die Eltern bekommen eine Mail.') : '')) ?>
                    <?php endif ?>
                    <?php if (!$a->anonymisiert_am): ?>
                        <?= Html::a('<i class="fa fa-pencil"></i> ' . Html::encode(Yii::t('KurseModule.base', 'Bearbeiten')), $space->createUrl('/kurse/anmeldung/bearbeiten', ['id' => $a->id]), ['class' => 'btn btn-sm btn-light']) ?>
                    <?php endif ?>
                    <details class="d-inline-block"><summary class="btn btn-sm btn-light"><?= Yii::t('KurseModule.base', 'Notiz') ?></summary>
                        <?= Html::beginForm($space->createUrl('/kurse/anmeldung/notiz', ['id' => $a->id]), 'post', ['class' => 'mt-1 d-flex gap-1']) ?>
                        <?= Html::textInput('notiz', $a->notiz, ['class' => 'form-control form-control-sm', 'maxlength' => 1000, 'placeholder' => Yii::t('KurseModule.base', 'nur für Kursverwalter sichtbar')]) ?>
                        <?= Html::submitButton(Html::encode(Yii::t('KurseModule.base', 'Speichern')), ['class' => 'btn btn-sm btn-primary']) ?>
                        <?= Html::endForm() ?>
                    </details>
                </div>
            </div>
        <?php endforeach ?>
    </div>
</div>
