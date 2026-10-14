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
Guten Tag <?= $name ?>,

es gibt einen neuen Stand zu Ihrer Mängelmeldung:

Nummer: <?= $mangel->getContentId() ?>

Kategorie: <?= $mangel->kategorie ?>

Ort: <?= $mangel->getOrt() ?>

Neuer Stand: <?= $stand ?>

<?= $hinweis ? "Hinweis des Bauhofs: $hinweis\n" : '' ?>

<?= $gast ? 'Den Bearbeitungsstand sehen Sie auch auf der Karte:' : 'Zur Meldung:' ?>

<?= $link ?>


Sie erhalten diese E-Mail, weil Sie beim Melden Infos per E-Mail gewünscht haben. Keine Infos mehr? Antworten Sie einfach auf diese E-Mail.

--
Rund um Uffni – Bürgerplattform der Bürgerliste Uffenheim e. V., kein Angebot der Stadt Uffenheim.
