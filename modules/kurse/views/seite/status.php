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
        <p class="grau klein" style="margin:0"><?= Yii::t('KurseModule.base', 'Anmeldung {nummer}', ['nummer' => Html::encode($a->getNummer())]) ?></p>
        <h1 style="margin:0 0 6px;font-size:1.6em"><?= Html::encode($a->getKindName()) ?></h1>
        <p style="margin:0 0 10px"><?= Html::a(Html::encode($kurs->titel), $kurs->elternUrl('kurs')) ?></p>
        <p><span class="marke-status s-<?= $farbe ?>" style="font-size:1em"><?= Html::encode($a->getStatusText()) ?><?=
            $a->status === Anmeldung::STATUS_WARTELISTE ? ' – ' . Yii::t('KurseModule.base', 'Platz {position}', ['position' => $a->getWartelistenPosition()]) : '' ?></span></p>

        <?php if ($kurs->status === Kurs::STATUS_ABGESAGT): ?>
            <div class="meldung fehler"><?= Yii::t('KurseModule.base', 'Der Kurs wurde abgesagt.') ?><?= $kurs->absage_grund ? '<br>' . nl2br(Html::encode($kurs->absage_grund), false) : '' ?></div>
        <?php elseif ($a->status === Anmeldung::STATUS_WARTELISTE): ?>
            <p><?= Yii::t('KurseModule.base', 'Wird ein Platz frei, rückt dein Kind automatisch nach und du bekommst eine Mail.') ?></p>
        <?php elseif ($a->status === Anmeldung::STATUS_STORNIERT && $a->storniert_von === 'voll'): ?>
            <p><?= Yii::t('KurseModule.base', 'Kurs und Warteliste waren bei deiner Bestätigung leider schon voll.') ?></p>
        <?php elseif ($a->status === Anmeldung::STATUS_STORNIERT && $a->storniert_von === 'doppelt'): ?>
            <p><?= Yii::t('KurseModule.base', 'Für dieses Kind gab es bei deiner Bestätigung schon eine andere bestätigte Anmeldung zu diesem Kurs. Diese hier zählt deshalb nicht.') ?></p>
        <?php endif ?>

        <dl class="fakten">
            <dt><?= Yii::t('KurseModule.base', 'Termine') ?></dt><dd><?= Html::encode($kurs->getTermineText()) ?><?= $kurs->termine_hinweis ? '<br>' . Html::encode($kurs->termine_hinweis) : '' ?></dd>
            <dt><?= Yii::t('KurseModule.base', 'Ort') ?></dt><dd><?= Html::encode($kurs->ort) ?></dd>
            <?php if ($a->status === Anmeldung::STATUS_PLATZ && $a->betrag !== null && (float)$a->betrag > 0): ?>
                <dt><?= Yii::t('KurseModule.base', 'Kursgebühr') ?></dt><dd><?= Kurs::euro($a->betrag) ?> ·
                    <?php if ($a->bezahlt_am): ?><strong style="color:var(--ok)"><?= Yii::t('KurseModule.base', 'bezahlt') ?></strong>
                    <?php elseif ($a->zahlart === Anmeldung::ZAHLART_BAR): ?><?= Yii::t('KurseModule.base', 'bar am ersten Kurstag') ?>
                    <?php else: ?><?= ($f = $a->zahlungsfrist()) ? Yii::t('KurseModule.base', 'per Überweisung bis {datum}', ['datum' => Kurs::datum($f)]) : Yii::t('KurseModule.base', 'per Überweisung') ?><br>
                        <?= Html::encode($kurs->kontoinhaber) ?> · IBAN <?= Html::encode(trim(chunk_split((string)$kurs->iban, 4, ' '))) ?><br>
                        <?= Yii::t('KurseModule.base', 'Verwendungszweck:') ?> <strong><?= Html::encode($a->getVerwendungszweck()) ?></strong>
                    <?php endif ?></dd>
            <?php endif ?>
            <dt><?= Yii::t('KurseModule.base', 'Eltern') ?></dt><dd><?= Html::encode($a->eltern_name) ?><?= $a->email ? ' · ' . Html::encode($a->email) : '' ?></dd>
            <dt><?= Yii::t('KurseModule.base', 'Kontakt') ?></dt><dd><?= Html::mailto(Html::encode($kurs->kontakt_email), $kurs->kontakt_email) ?></dd>
        </dl>

        <?php if (in_array($a->status, [Anmeldung::STATUS_PLATZ, Anmeldung::STATUS_WARTELISTE], true) && !$kurs->istVorbei()): ?>
            <?= Html::beginForm('', 'post', ['id' => 'abmelden']) ?>
            <?= Html::hiddenInput('aktion', 'abmelden') ?>
            <button type="submit" class="knopf rand"><?= Yii::t('KurseModule.base', 'Vom Kurs abmelden') ?></button>
            <?= Html::endForm() ?>
            <script <?= Html::nonce() ?>>
                document.getElementById('abmelden').addEventListener('submit', function (e) {
                    if (!confirm(<?= \yii\helpers\Json::htmlEncode(Yii::t('KurseModule.base', 'Wirklich abmelden? Der Platz geht an das nächste Kind auf der Warteliste.')) ?>)) { e.preventDefault(); }
                });
            </script>
        <?php endif ?>
        <p class="grau klein" style="margin-top:20px"><?= Yii::t('KurseModule.base', 'Diese Seite ist nur über den Link in deiner Mail erreichbar. Bitte gib ihn nicht weiter.') ?>
            <a href="<?= Html::encode($kurs->elternUrl('datenschutz')) ?>"><?= Yii::t('KurseModule.base', 'Datenschutzhinweise') ?></a></p>
    </div>
</div>
