<?php
/**
 * @var \humhub\modules\vereine\models\VereinBeitrag $beitrag
 * @var string $name
 * @var string $link
 * @var string $kontaktMail
 */
?>
Hallo <?= $name ?>,

Ihr Beitrag für „<?= $beitrag->verein->name ?>“ auf Rund um Uffni wurde geprüft und nicht veröffentlicht.

Begründung:
<?= $beitrag->begruendung ?>


Ihr Text:
<?= $beitrag->text ?>


Sie können den Beitrag überarbeiten und über die Vereinsseite neu einreichen:
<?= $link ?>


Fragen? Antworten Sie einfach auf diese Mail oder schreiben Sie an <?= $kontaktMail ?>.

Viele Grüße
Rund um Uffni – Bürgerplattform der Bürgerliste Uffenheim e. V.
