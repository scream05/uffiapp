<?php
/**
 * Anmeldeformular (ohne Skripte; Prüfung auf dem Server)
 * @var \yii\web\View $this
 * @var \humhub\modules\kurse\models\Anmeldung $a
 * @var \humhub\modules\kurse\models\Kurs $kurs
 * @var string|null $hinweis
 */

use humhub\helpers\Html;

$zahlarten = $kurs->hatPreis() ? $kurs->zahlarten() : [];
/** Eingabefeld mit Beschriftung, Hinweis und Fehlermeldung */
$feld = function (string $attr, string $eingabe, ?string $hint = null, bool $pflicht = true) use ($a) {
    $id = Html::getInputId($a, $attr);
    $fehler = $a->getFirstError($attr);
    return '<div class="feld' . ($fehler ? ' hat-fehler' : '') . '">'
        . '<label class="titel" for="' . $id . '">' . Html::encode($a->getAttributeLabel($attr)) . ($pflicht ? '' : ' <span class="grau">(freiwillig)</span>') . '</label>'
        . $eingabe
        . ($hint ? '<div class="hinweis">' . $hint . '</div>' : '')
        . ($fehler ? '<div class="fehlertext" id="' . $id . '-fehler">' . Html::encode($fehler) . '</div>' : '')
        . '</div>';
};
$opt = fn(string $attr, array $o = []) => $o + ($a->hasErrors($attr) ? ['aria-invalid' => 'true', 'aria-describedby' => Html::getInputId($a, $attr) . '-fehler'] : []);
$haken = function (string $attr, string $label) use ($a) {
    $fehler = $a->getFirstError($attr);
    return '<div class="feld' . ($fehler ? ' hat-fehler' : '') . '"><label class="wahl">'
        . Html::activeCheckbox($a, $attr, ['label' => false, 'uncheck' => '0'])
        . '<span>' . $label . '</span></label>'
        . ($fehler ? '<div class="fehlertext">' . Html::encode($fehler) . '</div>' : '') . '</div>';
};
?>
<div class="breite schmal abschnitt">
    <p><a href="<?= Html::encode($kurs->elternUrl('kurs')) ?>">← Zum Kurs</a></p>
    <div class="karte">
        <h1 style="margin:0 0 6px;font-size:1.6em">Anmeldung: <?= Html::encode($kurs->titel) ?></h1>
        <p class="grau"><?= Html::encode($kurs->getTermineText()) ?> · <?= Html::encode($kurs->ort) ?></p>

        <?php if ($hinweis): ?><div class="meldung fehler" role="alert"><?= Html::encode($hinweis) ?></div><?php endif ?>
        <?php if ($a->hasErrors()): ?><div class="meldung fehler" role="alert">Bitte prüfe die markierten Angaben.</div><?php endif ?>
        <?php if ($kurs->freiePlaetze() === 0): ?>
            <div class="meldung warn">Der Kurs ist gerade voll. Nach der Bestätigung kommt dein Kind auf die Warteliste und rückt automatisch nach, wenn ein Platz frei wird.</div>
        <?php endif ?>

        <?= Html::beginForm('', 'post', ['novalidate' => true]) ?>
        <fieldset>
            <legend>Kind</legend>
            <div class="reihe">
                <?= $feld('kind_vorname', Html::activeTextInput($a, 'kind_vorname', $opt('kind_vorname', ['maxlength' => 100, 'autocomplete' => 'off']))) ?>
                <?= $feld('kind_nachname', Html::activeTextInput($a, 'kind_nachname', $opt('kind_nachname', ['maxlength' => 100, 'autocomplete' => 'off']))) ?>
            </div>
            <?= $feld('kind_geburtsdatum', Html::activeInput('date', $a, 'kind_geburtsdatum', $opt('kind_geburtsdatum', ['max' => date('Y-m-d')]))) ?>
            <?= $feld('schwimmstand', Html::activeTextarea($a, 'schwimmstand', $opt('schwimmstand', ['rows' => 2, 'maxlength' => 1000,
                'placeholder' => 'z. B. wassergewöhnt, taucht schon, schwimmt mit Schwimmflügeln …'])), 'hilft der Kursleitung bei der Einteilung', false) ?>
            <?= $haken('mitglied', Html::encode($a->getAttributeLabel('mitglied'))) ?>
        </fieldset>

        <fieldset>
            <legend>Erziehungsberechtigte/r</legend>
            <?= $feld('eltern_name', Html::activeTextInput($a, 'eltern_name', $opt('eltern_name', ['maxlength' => 150, 'autocomplete' => 'name']))) ?>
            <div class="reihe">
                <?= $feld('email', Html::activeInput('email', $a, 'email', $opt('email', ['autocomplete' => 'email'])), 'Hierhin schicken wir den Bestätigungslink.') ?>
                <?= $feld('telefon', Html::activeInput('tel', $a, 'telefon', $opt('telefon', ['autocomplete' => 'tel']))) ?>
            </div>
            <?= $feld('notfall_telefon', Html::activeInput('tel', $a, 'notfall_telefon', $opt('notfall_telefon')), 'falls abweichend – z. B. wenn dein Kind von jemand anderem gebracht wird', false) ?>
        </fieldset>

        <?php if ($zahlarten): ?>
            <fieldset>
                <legend>Bezahlung</legend>
                <p style="margin-top:0">Kursgebühr: <?= Html::encode($kurs->getPreisText()) ?></p>
                <?php $fehler = $a->getFirstError('zahlart') ?>
                <div class="feld<?= $fehler ? ' hat-fehler' : '' ?>" role="radiogroup" aria-label="Zahlart">
                    <div class="wahl"><?= Html::activeRadioList($a, 'zahlart', $zahlarten, ['tag' => false, 'unselect' => '',
                        'item' => fn($i, $label, $name, $checked, $value) => '<label>' . Html::radio($name, $checked, ['value' => $value]) . '<span>' . Html::encode($label) . '</span></label>']) ?></div>
                    <?php if ($fehler): ?><div class="fehlertext"><?= Html::encode($fehler) ?></div><?php endif ?>
                </div>
            </fieldset>
        <?php endif ?>

        <div style="position:absolute;left:-5000px" aria-hidden="true"><?= Html::activeTextInput($a, 'webseite', ['tabindex' => -1, 'autocomplete' => 'off']) ?></div>

        <fieldset>
            <legend>Einverständnis</legend>
            <?php if ($kurs->bedingungen): ?>
                <details style="margin-bottom:10px"><summary>Teilnahmebedingungen lesen</summary><p><?= nl2br(Html::encode($kurs->bedingungen), false) ?></p></details>
                <?= $haken('bedingungen_ok', 'Ich habe die Teilnahmebedingungen gelesen und bin einverstanden.') ?>
            <?php else: ?>
                <?= Html::hiddenInput(Html::getInputName($a, 'bedingungen_ok'), '1') ?>
            <?php endif ?>
            <?= $haken('datenschutz_ok', 'Ich habe die ' . Html::a('Datenschutzhinweise', $kurs->elternUrl('datenschutz'), ['target' => '_blank']) . ' gelesen und bin erziehungsberechtigt.') ?>
        </fieldset>

        <button type="submit" class="knopf">Anmeldung absenden</button>
        <p class="grau klein">Du bekommst gleich eine Mail mit einem Link. Erst mit dem Klick auf diesen Link ist die Anmeldung gültig.</p>
        <?= Html::endForm() ?>
    </div>
</div>
