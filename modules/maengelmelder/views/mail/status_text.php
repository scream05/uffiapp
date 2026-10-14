<?php
/**
 * Info an die meldende Person bei Freigabe/Statusänderung (Text). Siehe Buergerinfo::mail()
 *
 * @var string $name
 * @var \humhub\modules\maengelmelder\models\Mangel $mangel
 * @var string $stand
 * @var string|null $hinweis
 * @var string $link
 * @var string $kontaktMail
 * @var bool $gast
 */
?>
Hallo <?= $name ?>,

es gibt einen neuen Stand zu deiner Mängelmeldung:

Nummer: <?= $mangel->getContentId() ?>

Kategorie: <?= $mangel->kategorie ?>

Ort: <?= $mangel->getOrt() ?>

Neuer Stand: <?= $stand ?>

<?= $hinweis ? "Hinweis des Bauhofs: $hinweis\n" : '' ?>

<?= $gast ? 'Den Bearbeitungsstand siehst du auch auf der Karte:' : 'Zur Meldung:' ?>

<?= $link ?>


Du kannst auf diese E-Mail antworten; deine Antwort geht an den Bauhof.
Du bekommst diese E-Mail, weil du beim Melden Infos per E-Mail gewünscht hast. Keine Infos mehr? Antworte mit STOPP.

--
Rund um Uffni – Bürgerplattform der Bürgerliste Uffenheim e. V., kein Angebot der Stadt Uffenheim.
