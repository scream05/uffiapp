<?php
/**
 * Info an die meldende Person bei Freigabe/Statusänderung (Text). Siehe Buergerinfo::mail()
 * Wird in der Sprache der meldenden Person erzeugt (MaengelKontakt::inSprache).
 *
 * @var string $name
 * @var \humhub\modules\maengelmelder\models\Mangel $mangel
 * @var string $stand
 * @var string|null $hinweis
 * @var string $link
 * @var string $kontaktMail
 * @var bool $gast
 */
$t = fn($s, $p = []) => Yii::t('MaengelmelderModule.base', $s, $p);
?>
<?= $t('Hallo {name},', ['name' => $name]) ?>


<?= $t('es gibt einen neuen Stand zu deiner Mängelmeldung:') ?>


<?= $t('Nummer: {nr}', ['nr' => $mangel->getContentId()]) ?>

<?= $t('Kategorie: {kategorie}', ['kategorie' => Yii::$app->getModule('maengelmelder')->kategorieLabel($mangel->kategorie)]) ?>

<?= $t('Ort: {ort}', ['ort' => $mangel->getOrt()]) ?>

<?= $t('Neuer Stand: {stand}', ['stand' => $stand]) ?>

<?= $hinweis ? $t('Hinweis des Bauhofs: {hinweis}', ['hinweis' => $hinweis]) . "\n" : '' ?>

<?= $gast ? $t('Den Bearbeitungsstand siehst du auch auf der Karte:') : $t('Zur Meldung:') ?>

<?= $link ?>


<?= $t('Du kannst auf diese E-Mail antworten; deine Antwort geht an den Bauhof.') ?>

<?= $t('Du bekommst diese E-Mail, weil du beim Melden Infos per E-Mail gewünscht hast. Keine Infos mehr? Antworte mit STOPP.') ?>


--
<?= $t('Rund um Uffni – Bürgerplattform der Bürgerliste Uffenheim e. V., kein Angebot der Stadt Uffenheim.') ?>

