<?php
/**
 * Eingangsbestätigung an Gäste (Text). Variablen siehe MeldungController::gastBestaetigen()
 *
 * @var string $name
 * @var \humhub\modules\maengelmelder\models\Mangel $mangel
 * @var int $fotos
 * @var string $karteUrl
 * @var string $kontaktMail
 * @var bool $infoMail
 */
?>
Hallo <?= $name ?>,

vielen Dank für deine Mängelmeldung. Sie ist bei uns eingegangen:

Nummer: <?= $mangel->getContentId() ?>

Kategorie: <?= $mangel->kategorie ?>

Ort: <?= $mangel->getOrt() ?>

Beschreibung: <?= $mangel->beschreibung ?>

<?= $fotos ? "Fotos: $fotos\n" : '' ?>

Der Bauhof prüft deine Meldung und schaltet sie danach auf der Karte frei. Dort siehst du auch den Bearbeitungsstand:
<?= $karteUrl ?>


Bei Rückfragen meldet sich der Bauhof über die angegebenen Kontaktdaten.<?= $infoMail ? ' Über jede Änderung am Bearbeitungsstand informieren wir dich per E-Mail.' : '' ?>

Du kannst auf diese E-Mail antworten, z. B. mit einer Ergänzung oder einem weiteren Foto; deine Antwort geht an den Bauhof.

Du bekommst diese E-Mail, weil deine Adresse bei einer Mängelmeldung angegeben wurde. Warst du das nicht? Dann schreib bitte an <?= $kontaktMail ?>, wir löschen die Angaben.

--
Rund um Uffni – Bürgerplattform der Bürgerliste Uffenheim e. V., kein Angebot der Stadt Uffenheim.
