<?php
/**
 * Bestätigung der E-Mail-Adresse einer Gastmeldung (Text). Bewusst ohne Text aus der Meldung – die Adresse
 * könnte von jemand anderem eingetragen worden sein. Siehe MeldungController::bestaetigungsMail()
 * Wird in der Sprache der meldenden Person erzeugt (MaengelKontakt::inSprache).
 *
 * @var string $name
 * @var int $nr
 * @var string $link
 * @var int $stunden
 * @var string $kontaktMail
 */
$t = fn($s, $p = []) => Yii::t('MaengelmelderModule.base', $s, $p);
?>
<?= $t('Hallo {name},', ['name' => $name]) ?>


<?= $t('bei Rund um Uffni ist eine Mängelmeldung (Nr. {nr}) mit dieser E-Mail-Adresse eingegangen.', ['nr' => $nr]) ?>


<?= $t('Bitte bestätige deine Adresse mit diesem Link (gültig {stunden} Stunden):', ['stunden' => $stunden]) ?>


<?= $link ?>


<?= $t('Erst danach schicken wir dir Infos zum Bearbeitungsstand und Rückfragen des Bauhofs per E-Mail. Die Meldung selbst ist schon beim Bauhof.') ?>


<?= $t('Du hast nichts gemeldet? Dann ignorier diese E-Mail einfach – wir schreiben dir nicht noch einmal. Fragen: {mail}', ['mail' => $kontaktMail]) ?>



--
<?= $t('Rund um Uffni – Bürgerplattform der Bürgerliste Uffenheim e. V., kein Angebot der Stadt Uffenheim.') ?>

