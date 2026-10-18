<?php
/**
 * Ablehnung eines Beitrags (Text), in der Sprache des Empfängers (BeitragDienst::ablehnungsMail).
 * Begründung und Beitragstext bleiben wie geschrieben.
 *
 * @var \humhub\modules\vereine\models\VereinBeitrag $beitrag
 * @var string $name
 * @var string $link
 * @var string $kontaktMail
 */
$t = fn($s, $p = []) => Yii::t('VereineModule.base', $s, $p);
?>
<?= $t('Hallo {name},', ['name' => $name]) ?>


<?= $t('dein Beitrag für „{name}“ auf Rund um Uffni wurde geprüft und nicht veröffentlicht.', ['name' => $beitrag->verein->name]) ?>


<?= $t('Begründung:') ?>

<?= $beitrag->begruendung ?>



<?= $t('Dein Text:') ?>

<?= $beitrag->text ?>



<?= $t('Du kannst den Beitrag überarbeiten und über die Vereinsseite neu einreichen:') ?>

<?= $link ?>



<?= $t('Fragen? Antworte einfach auf diese E-Mail oder schreib an {mail}.', ['mail' => $kontaktMail]) ?>


<?= $t('Viele Grüße') ?>

<?= $t('Rund um Uffni – Bürgerplattform der Bürgerliste Uffenheim e. V.') ?>

