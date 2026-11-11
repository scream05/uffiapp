<?php
/**
 * Datenschutzhinweise der eigenen Kursseite (/kurse/<slug>/datenschutz): Verein verantwortlich,
 * Bürgerliste Uffenheim e. V. als Auftragsverarbeiter (Plattform „Rund um Uffni“).
 * Inhaltlich abgeleitet aus legal/datenschutz.md (Abschnitte 2–4, 7, 12, 14, 16) – bei Änderungen dort mitpflegen.
 *
 * @var \yii\web\View $this
 */

use humhub\helpers\Html;

/** @var \humhub\modules\kurse\controllers\SeiteController $c */
$c = $this->context;
$verein = $c->verein;
$seite = $c->seite;
$name = Html::encode($verein->name);
$email = trim((string)$verein->email);
$modul = Yii::$app->getModule('kurse');
?>
<style <?= Html::nonce() ?>>
    .datenschutz h2 { font-size: 1.15em; margin: 1.6em 0 .5em; }
    .datenschutz p { margin: 0 0 .8em; }
</style>
<div class="breite schmal abschnitt">
    <p><a href="<?= Html::encode($seite->seiteUrl()) ?>">← Zur Kursseite</a></p>
    <div class="karte datenschutz">
        <h1 style="margin-top:0;font-size:1.6em">Datenschutz</h1>
        <p class="grau klein">Stand: 11. November 2026</p>

        <h2>1. Wer ist verantwortlich?</h2>
        <p>Verantwortlich für diese Kursseite und die Kursanmeldungen ist <strong><?= $name ?></strong><?= $email !== '' ? ' (E-Mail: ' . Html::mailto(Html::encode($email), $email) . ')' : '' ?>.
            <?= $seite->impressum_url ? 'Anschrift und Vertretung findest du im ' . Html::a('Impressum', $seite->impressum_url, ['rel' => 'noopener']) . '.' : '' ?></p>
        <p>Die Seite läuft technisch auf der Bürgerplattform „Rund um Uffni“ der Bürgerliste Uffenheim e. V., Albrecht-Dürer-Str. 30, 97215 Uffenheim.
            Die Bürgerliste verarbeitet die Daten nur im Auftrag und nach Weisung des Vereins (Auftragsverarbeitung, Art. 28 DSGVO)
            und nutzt sie nicht für eigene Zwecke – auch nicht für ihre politische Arbeit oder für Werbung.</p>

        <h2>2. Server und Dienste</h2>
        <p>Die Seite läuft auf einem eigenen Server der Bürgerliste in Deutschland. Schriften und Programmteile kommen vom eigenen Server.
            Es gibt keine Werbung, keine Tracking- oder Analysedienste anderer Anbieter und keine eingebetteten Inhalte Dritter.</p>

        <h2>3. Beim Aufruf der Seite</h2>
        <p>Der Server verarbeitet technisch notwendige Daten: IP-Adresse, Datum und Uhrzeit, aufgerufene Adresse sowie Angaben zu Browser und Betriebssystem.
            Das ist nötig, um die Seite auszuliefern, sie vor Missbrauch zu schützen und Fehler zu beheben. Fehlerprotokolle, die diese Daten enthalten können,
            werden nach spätestens 30 Tagen gelöscht. Rechtsgrundlage ist das berechtigte Interesse an einem sicheren Betrieb (Art. 6 Abs. 1 lit. f DSGVO).</p>
        <p><strong>Besucherzählung:</strong> Seitenaufrufe werden auf dem eigenen Server gezählt – ohne Cookies und ohne Speicherung in deinem Browser.
            Gespeichert werden Zeitpunkt, aufgerufene Seite, Geräteart, Browser- und Betriebssystemfamilie und bei Beginn eines Besuchs die Domain der verweisenden Website.
            Deine IP-Adresse wird nicht gespeichert, sondern nur mit einem täglich wechselnden Zufallswert zu einer nicht umkehrbaren Prüfsumme verrechnet,
            damit mehrere Aufrufe eines Tages als ein Besuch zählen. Die Einträge werden nach 14 Monaten gelöscht (Art. 6 Abs. 1 lit. f DSGVO).
            Sendet dein Browser „Do Not Track“ oder „Global Privacy Control“, zählen wir dich nicht.</p>

        <h2>4. Cookies</h2>
        <p>Es werden nur technisch notwendige Cookies gesetzt: <code>PHPSESSID</code> (Sitzung, z. B. für Hinweise nach dem Absenden eines Formulars)
            und <code>_csrf</code> (Schutz der Formulare vor Missbrauch). Beide werden beim Schließen des Browsers gelöscht.
            Eine Einwilligung ist dafür nicht nötig (§ 25 Abs. 2 Nr. 2 TDDDG).</p>

        <h2>5. Kursanmeldung</h2>
        <p><strong>Welche Daten:</strong> Vor- und Nachname und Geburtsdatum des Kindes, freiwillige Angaben zum Schwimmstand, ob das Kind Vereinsmitglied ist,
            Name, E-Mail-Adresse und Telefonnummer der/des Erziehungsberechtigten, freiwillig eine Notfall-Telefonnummer, die gewählte Zahlart, Betrag und Zahlungsstatus
            sowie die Zeitpunkte von Anmeldung, Bestätigung und Abmeldung. Gesundheitsdaten und Fotos fragen wir nicht ab – wichtige Hinweise klärst du bitte direkt mit der Kursleitung.</p>
        <p><strong>Wozu:</strong> Platzvergabe und Warteliste, Organisation des Kurses, Kontakt bei Änderungen und im Notfall, Erinnerungen an Kursbeginn und Zahlung.
            Rechtsgrundlage ist die Anmeldung zum Kurs (Art. 6 Abs. 1 lit. b DSGVO).</p>
        <p><strong>Ablauf:</strong> Nach dem Absenden bekommst du eine E-Mail mit einem Bestätigungslink; erst mit der Bestätigung ist die Anmeldung gültig.
            Danach bekommst du E-Mails zur Platzzusage oder Warteliste, beim Nachrücken, kurz vor Kursbeginn, gegebenenfalls eine Zahlungserinnerung, bei einer Absage
            sowie Rundmails des Vereins zum Kurs. Antworten auf diese E-Mails gehen direkt an den Verein. Über den persönlichen Link in den E-Mails siehst du deine Anmeldung
            und kannst dich jederzeit selbst abmelden.</p>
        <p><strong>Wer sieht die Daten:</strong> die vom Verein benannten Kursverwalter und die technische Administration der Plattform.
            Kursverwalter können die Teilnehmerliste herunterladen, etwa für die Anwesenheit am Beckenrand; für diese Kopien ist der Verein verantwortlich.
            Die Daten werden nicht an Dritte weitergegeben und nicht für Werbung genutzt.</p>
        <p><strong>Wie lange:</strong> Nicht bestätigte Anmeldungen werden nach <?= (int)$modul->unbestaetigtStunden ?> Stunden automatisch gelöscht.
            Alle Angaben zu Kind und Erziehungsberechtigten werden etwa <?= round($modul->loeschenNachTagen / 30) ?> Monate nach Kursende automatisch gelöscht;
            übrig bleiben nur Anmeldenummer, Status, Betrag und Zahlungsvermerk ohne Namen. Für die Buchhaltung des Vereins gelten dessen eigene Aufbewahrungspflichten.
            Um Missbrauch zu verhindern, wird kurzzeitig gezählt, wie viele Anmeldungen von einem Anschluss (IP-Adresse, höchstens eine Stunde)
            und für eine E-Mail-Adresse (nur als Prüfsumme, höchstens einen Tag) kommen (Art. 6 Abs. 1 lit. f DSGVO).
            Wird ein Kind, das schon angemeldet ist, mit einer anderen E-Mail-Adresse noch einmal angemeldet, legen wir keine zweite Anmeldung an
            und informieren die Adresse der bestehenden Anmeldung darüber.</p>

        <h2>6. E-Mail-Versand</h2>
        <p>Die E-Mails zur Kursanmeldung werden über Google Workspace der Google Ireland Limited, Gordon House, Barrow Street, Dublin 4, Irland, verschickt.
            Mit Google besteht ein Vertrag zur Auftragsverarbeitung (Art. 28 DSGVO). Eine Übermittlung in die USA ist nicht auszuschließen;
            die Google LLC ist nach dem EU-US Data Privacy Framework zertifiziert (Angemessenheitsbeschluss, Art. 45 DSGVO).</p>

        <h2>7. Datensicherung</h2>
        <p>Zum Schutz vor Datenverlust werden die Daten täglich gesichert, auf dem Server und auf einem zweiten Speichergerät im selben lokalen Netzwerk
            (nicht bei einem externen Anbieter). Die Sicherungen werden nach 14 Tagen automatisch gelöscht (Art. 6 Abs. 1 lit. f DSGVO).</p>

        <h2>8. Deine Rechte</h2>
        <p>Du hast das Recht auf Auskunft (Art. 15 DSGVO), Berichtigung (Art. 16), Löschung (Art. 17), Einschränkung der Verarbeitung (Art. 18),
            Datenübertragbarkeit (Art. 20) und Widerspruch gegen Verarbeitungen auf Grundlage berechtigter Interessen (Art. 21 DSGVO).
            Wende dich dafür an den Verein<?= $email !== '' ? ' unter ' . Html::mailto(Html::encode($email), $email) : '' ?>.
            Abmelden kannst du dich jederzeit über den Link in deiner Bestätigungsmail.</p>
        <p>Du kannst dich außerdem bei einer Datenschutz-Aufsichtsbehörde beschweren, zum Beispiel beim Bayerischen Landesamt für Datenschutzaufsicht (BayLDA),
            Promenade 18, 91522 Ansbach, www.lda.bayern.de.</p>
    </div>
</div>
