<?php
/**
 * @var \humhub\modules\gewerbe\models\BetriebBeitrag $beitrag
 * @var string $name
 * @var string $link
 * @var string $kontaktMail
 */
?>
Hallo <?= $name ?>,

dein Beitrag für „<?= $beitrag->betrieb->name ?>“ auf Rund um Uffni wurde geprüft und nicht veröffentlicht.

Begründung:
<?= $beitrag->begruendung ?>


Dein Text:
<?= $beitrag->text ?>


Du kannst den Beitrag überarbeiten und über die Betriebsseite neu einreichen:
<?= $link ?>


Fragen? Antworte einfach auf diese E-Mail oder schreib an <?= $kontaktMail ?>.

Viele Grüße
Rund um Uffni – Bürgerplattform der Bürgerliste Uffenheim e. V.
