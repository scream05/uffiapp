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
        <h1 style="font-size:1.4em"><?= Yii::t('KurseModule.base', 'Anmeldung {nummer}', ['nummer' => Html::encode($a->getNummer())]) ?></h1>
        <p class="mb-1"><strong><?= Html::encode($a->getKindName()) ?></strong> · <?= Html::encode($kurs->titel) ?> (<?= Html::encode($kurs->verein->name) ?>)</p>
        <p><span class="badge text-bg-<?= $farbe ?>" style="font-size:1em"><?= Html::encode($a->getStatusText()) ?><?=
            $a->status === Anmeldung::STATUS_WARTELISTE ? ' – ' . Yii::t('KurseModule.base', 'Platz {position}', ['position' => $a->getWartelistenPosition()]) : '' ?></span></p>

        <?php if ($kurs->status === Kurs::STATUS_ABGESAGT): ?>
            <div class="alert alert-danger"><?= Yii::t('KurseModule.base', 'Der Kurs wurde abgesagt.') ?><?= $kurs->absage_grund ? '<br>' . nl2br(Html::encode($kurs->absage_grund), false) : '' ?></div>
        <?php elseif ($a->status === Anmeldung::STATUS_WARTELISTE): ?>
            <p><?= Yii::t('KurseModule.base', 'Wird ein Platz frei, rückt dein Kind automatisch nach und du bekommst eine Mail.') ?></p>
        <?php elseif ($a->status === Anmeldung::STATUS_STORNIERT && $a->storniert_von === 'voll'): ?>
            <p><?= Yii::t('KurseModule.base', 'Kurs und Warteliste waren bei deiner Bestätigung leider schon voll.') ?></p>
        <?php elseif ($a->status === Anmeldung::STATUS_STORNIERT && $a->storniert_von === 'doppelt'): ?>
            <p><?= Yii::t('KurseModule.base', 'Für dieses Kind gab es bei deiner Bestätigung schon eine andere bestätigte Anmeldung zu diesem Kurs. Diese hier zählt deshalb nicht.') ?></p>
        <?php endif ?>

        <dl class="kurs-fakten" style="display:grid;grid-template-columns:max-content 1fr;gap:4px 14px">
            <dt><?= Yii::t('KurseModule.base', 'Termine') ?></dt><dd class="m-0"><?= Html::encode($kurs->getTermineText()) ?><?= $kurs->termine_hinweis ? '<br>' . Html::encode($kurs->termine_hinweis) : '' ?></dd>
            <dt><?= Yii::t('KurseModule.base', 'Ort') ?></dt><dd class="m-0"><?= Html::encode($kurs->ort) ?></dd>
            <?php if ($a->status === Anmeldung::STATUS_PLATZ && $a->betrag !== null && (float)$a->betrag > 0): ?>
                <dt><?= Yii::t('KurseModule.base', 'Kursgebühr') ?></dt><dd class="m-0"><?= Kurs::euro($a->betrag) ?> ·
                    <?php if ($a->bezahlt_am): ?><span class="text-success"><?= Yii::t('KurseModule.base', 'bezahlt') ?></span>
                    <?php elseif ($a->zahlart === Anmeldung::ZAHLART_BAR): ?><?= Yii::t('KurseModule.base', 'bar am ersten Kurstag') ?>
                    <?php else: ?><?= ($f = $a->zahlungsfrist()) ? Yii::t('KurseModule.base', 'per Überweisung bis {datum}', ['datum' => Kurs::datum($f)]) : Yii::t('KurseModule.base', 'per Überweisung') ?><br>
                        <?= Html::encode($kurs->kontoinhaber) ?> · IBAN <?= Html::encode(trim(chunk_split((string)$kurs->iban, 4, ' '))) ?><br>
                        <?= Yii::t('KurseModule.base', 'Verwendungszweck:') ?> <strong><?= Html::encode($a->getVerwendungszweck()) ?></strong>
                    <?php endif ?></dd>
            <?php endif ?>
            <dt><?= Yii::t('KurseModule.base', 'Eltern') ?></dt><dd class="m-0"><?= Html::encode($a->eltern_name) ?><?= $a->email ? ' · ' . Html::encode($a->email) : '' ?></dd>
            <dt><?= Yii::t('KurseModule.base', 'Kontakt') ?></dt><dd class="m-0"><?= Html::mailto(Html::encode($kurs->kontakt_email), $kurs->kontakt_email) ?></dd>
        </dl>

        <?php if (in_array($a->status, [Anmeldung::STATUS_PLATZ, Anmeldung::STATUS_WARTELISTE], true) && !$kurs->istVorbei()): ?>
            <?= Html::beginForm() ?>
            <?= Html::hiddenInput('aktion', 'abmelden') ?>
            <?= Html::submitButton(Html::encode(Yii::t('KurseModule.base', 'Vom Kurs abmelden')), ['class' => 'btn btn-light', 'data-confirm' => Yii::t('KurseModule.base', 'Wirklich abmelden? Der Platz geht an das nächste Kind auf der Warteliste.')]) ?>
            <?= Html::endForm() ?>
        <?php endif ?>
        <p class="small text-muted mt-3"><?= Yii::t('KurseModule.base', 'Diese Seite ist nur über den Link in deiner Mail erreichbar. Bitte gib ihn nicht weiter.') ?>
            <?= Html::a(Html::encode(Yii::t('KurseModule.base', 'Datenschutzhinweise')), $kurs->createUrl('/kurse/kurs/datenschutz', ['id' => $kurs->id])) ?></p>
    </div>
</div>
