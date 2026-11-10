<?php
/**
 * Text der Datenschutzhinweise zur Kursanmeldung (Space-Seite und eigene Vereinsseite)
 * @var \humhub\modules\kurse\models\Kurs $kurs
 */

use humhub\helpers\Html;

$tage = Yii::$app->getModule('kurse')->loeschenNachTagen;
$verein = Html::encode($kurs->verein->name);
?>
<p><strong>Verantwortlich</strong> für die Anmeldedaten zu diesem Kurs ist der Verein <?= $verein ?>
    (Kontakt: <?= Html::mailto(Html::encode($kurs->kontakt_email), $kurs->kontakt_email) ?>).
    Die Plattform „Rund um Uffni“ der Bürgerliste Uffenheim e. V. stellt die Anmeldung technisch bereit und verarbeitet die Daten
    nur im Auftrag und nach Weisung des Vereins (Auftragsverarbeitung, Art. 28 DSGVO).</p>
<p><strong>Welche Daten:</strong> Vor- und Nachname und Geburtsdatum des Kindes, freiwillige Angaben zum Schwimmstand,
    Name, E-Mail-Adresse und Telefonnummer(n) der/des Erziehungsberechtigten, Mitgliedschaft, Zahlart und Zahlungsstatus,
    Zeitpunkte von Anmeldung, Bestätigung und Abmeldung. Gesundheitsdaten fragen wir nicht ab – wichtige Hinweise klärst du bitte direkt mit der Kursleitung.</p>
<p><strong>Wozu:</strong> Platzvergabe und Warteliste, Organisation des Kurses, Kontakt bei Änderungen und im Notfall,
    Erinnerungen an Kursbeginn und Zahlung. Rechtsgrundlage ist die Anmeldung zum Kurs (Art. 6 Abs. 1 lit. b DSGVO).</p>
<p><strong>Wer sieht die Daten:</strong> nur die vom Verein benannten Kursverwalter und die Administration der Plattform.
    Die Daten werden nicht an Dritte weitergegeben und nicht für Werbung genutzt. Die Mails verschickt unser Mailserver.</p>
<p><strong>Wie lange:</strong> Nicht bestätigte Anmeldungen löschen wir nach <?= Yii::$app->getModule('kurse')->unbestaetigtStunden ?> Stunden.
    Alle Angaben zu Kind und Eltern löschen wir automatisch etwa <?= round($tage / 30) ?> Monate nach Kursende; übrig bleiben nur Anmeldenummer, Betrag und Zahlungsvermerk.
    Für die Buchhaltung des Vereins gelten dessen eigene Aufbewahrungspflichten.</p>
<p><strong>Deine Rechte:</strong> Auskunft, Berichtigung, Löschung, Einschränkung, Datenübertragbarkeit und Widerspruch;
    außerdem kannst du dich bei einer Datenschutz-Aufsichtsbehörde beschweren (in Bayern für Vereine: Bayerisches Landesamt für Datenschutzaufsicht).
    Wende dich dazu an den Verein unter der oben genannten Adresse. Abmelden kannst du dich jederzeit über den Link in deiner Bestätigungsmail.</p>
<p class="small text-muted">Allgemeine Hinweise zur Plattform: <?= Html::a('Datenschutzerklärung', ['/legal/page/view', 'pageKey' => 'privacy']) ?>.</p>
