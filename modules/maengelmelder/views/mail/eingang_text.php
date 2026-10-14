<?php
/**
 * Eingangsbestätigung an Gäste (Text). Variablen siehe MeldungController::gastBestaetigen()
 *
 * @var string $name
 * @var \humhub\modules\maengelmelder\models\Mangel $mangel
 * @var int $fotos
 * @var string $karteUrl
 * @var string $kontaktMail
 */
?>
Guten Tag <?= $name ?>,

vielen Dank für Ihre Mängelmeldung. Sie ist bei uns eingegangen:

Nummer: <?= $mangel->getContentId() ?>

Kategorie: <?= $mangel->kategorie ?>

Ort: <?= $mangel->getOrt() ?>

Beschreibung: <?= $mangel->beschreibung ?>

<?= $fotos ? "Fotos: $fotos\n" : '' ?>

Der Bauhof prüft Ihre Meldung und schaltet sie danach auf der Karte frei. Dort sehen Sie auch den Bearbeitungsstand:
<?= $karteUrl ?>


Bei Rückfragen meldet sich der Bauhof über die angegebenen Kontaktdaten.

Sie erhalten diese E-Mail, weil Ihre Adresse bei einer Mängelmeldung angegeben wurde. Waren Sie das nicht? Dann schreiben Sie bitte an <?= $kontaktMail ?>, wir löschen die Angaben.

--
Rund um Uffni – Bürgerplattform der Bürgerliste Uffenheim e. V., kein Angebot der Stadt Uffenheim.
