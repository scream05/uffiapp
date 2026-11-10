<?php
/**
 * Kursseite mit Anmeldeknopf
 * @var \yii\web\View $this
 * @var \humhub\modules\kurse\models\Kurs $kurs
 */

use humhub\helpers\Html;
use humhub\modules\kurse\models\Kurs;
use humhub\modules\kurse\Module;

[$label, $farbe] = $kurs->getStatusLabel();
$gesperrt = $kurs->anmeldungGesperrt();
$seite = $this->context->seite;
?>
<div class="breite schmal abschnitt">
    <p><a href="<?= Html::encode($seite->seiteUrl()) ?>#kurse">← Alle Kurse</a></p>
    <div class="karte">
        <div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;align-items:flex-start">
            <div>
                <h1 style="margin:0 0 4px;font-size:1.8em"><?= Html::encode($kurs->titel) ?></h1>
                <?php if ($kurs->niveau): ?><p class="grau" style="margin:0"><?= Html::encode($kurs->niveau) ?></p><?php endif ?>
            </div>
            <span class="marke-status s-<?= $kurs->status === Kurs::STATUS_ENTWURF ? 'default' : $farbe ?>"><?= Html::encode($kurs->status === Kurs::STATUS_ENTWURF ? 'Entwurf' : $label) ?></span>
        </div>

        <?php if (Module::kannVerwalten($kurs->verein_id)): ?>
            <div class="meldung klein">Du verwaltest diesen Kurs: <?= Html::a('Teilnehmer, Bearbeiten, Veröffentlichen …', $kurs->getUrl()) ?></div>
        <?php endif ?>
        <?php if ($kurs->status === Kurs::STATUS_ABGESAGT): ?>
            <div class="meldung fehler">Dieser Kurs wurde abgesagt.<?= $kurs->absage_grund ? '<br>' . nl2br(Html::encode($kurs->absage_grund), false) : '' ?></div>
        <?php endif ?>

        <?= $this->render('_fakten', ['kurs' => $kurs]) ?>

        <?php if ($kurs->beschreibung): ?>
            <p><?= nl2br(Html::encode($kurs->beschreibung), false) ?></p>
        <?php endif ?>

        <?php if ($gesperrt === null): ?>
            <p style="margin-top:24px"><a class="knopf" href="<?= Html::encode($kurs->elternUrl('anmelden')) ?>"><?= $kurs->freiePlaetze() > 0 ? 'Jetzt anmelden' : 'Auf die Warteliste setzen' ?> <span aria-hidden="true">→</span></a></p>
            <p class="grau klein">Plätze werden in der Reihenfolge vergeben, in der die Anmeldungen per E-Mail bestätigt werden.</p>
        <?php elseif ($kurs->status !== Kurs::STATUS_ENTWURF && $kurs->status !== Kurs::STATUS_ABGESAGT): ?>
            <div class="meldung"><?= Html::encode($gesperrt) ?></div>
        <?php endif ?>

        <?php if ($kurs->bedingungen): ?>
            <details style="margin-top:20px"><summary>Teilnahmebedingungen</summary><p><?= nl2br(Html::encode($kurs->bedingungen), false) ?></p></details>
        <?php endif ?>
        <p class="klein" style="margin-top:18px"><a href="<?= Html::encode($kurs->elternUrl('datenschutz')) ?>">Datenschutzhinweise zur Kursanmeldung</a></p>
    </div>
</div>
