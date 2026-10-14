<?php
/**
 * Bestätigung der E-Mail-Adresse einer Gastmeldung (Text). Bewusst ohne Text aus der Meldung – die Adresse
 * könnte von jemand anderem eingetragen worden sein. Siehe MeldungController::bestaetigungsMail()
 *
 * @var string $name
 * @var int $nr
 * @var string $link
 * @var int $stunden
 * @var string $kontaktMail
 */
?>
Hallo <?= $name ?>,

bei Rund um Uffni ist eine Mängelmeldung (Nr. <?= $nr ?>) mit dieser E-Mail-Adresse eingegangen.

Bitte bestätige deine Adresse mit diesem Link (gültig <?= $stunden ?> Stunden):

<?= $link ?>


Erst danach schicken wir dir Infos zum Bearbeitungsstand und Rückfragen des Bauhofs per E-Mail. Die Meldung selbst ist schon beim Bauhof.

Du hast nichts gemeldet? Dann ignorier diese E-Mail einfach – wir schreiben dir nicht noch einmal. Fragen: <?= $kontaktMail ?>


--
Rund um Uffni – Bürgerplattform der Bürgerliste Uffenheim e. V., kein Angebot der Stadt Uffenheim.
