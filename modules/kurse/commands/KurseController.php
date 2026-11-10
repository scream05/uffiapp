<?php

namespace humhub\modules\kurse\commands;

use humhub\modules\kurse\components\Dienst;
use humhub\modules\kurse\models\Anmeldung;
use humhub\modules\kurse\models\Kurs;
use humhub\modules\kurse\Module;
use Yii;
use yii\console\Controller;
use yii\console\ExitCode;

/**
 * Regelmäßige Aufgaben des Kursmoduls (Crontab alle 15 Minuten: /app/yii kurse/cron):
 * - nicht bestätigte Anmeldungen nach 48 Stunden löschen
 * - Erinnerung x Tage vor Kursbeginn (einmal je Anmeldung, nur tagsüber)
 * - Zahlungserinnerung einmal, wenn die Überweisungsfrist abgelaufen ist
 * - Kinderdaten ein halbes Jahr nach Kursende anonymisieren
 *
 * Einstellungen: /app/yii kurse/oeffentlich 0|1 (Kursseiten für alle sichtbar oder nur für Admins/Kursverwalter)
 *
 * @property Module $module
 */
class KurseController extends Controller
{
    /** @param int $probe 1 = Mails nur als Datei in @runtime/mail (Test) */
    public function actionCron($probe = 0)
    {
        if ($probe) {
            Yii::$app->mailer->useFileTransport = true;
        }
        /** @var Module $module */
        $module = Yii::$app->getModule('kurse');
        $log = [];

        $alt = date('Y-m-d H:i:s', strtotime('-' . $module->unbestaetigtStunden . ' hours'));
        $n = Anmeldung::deleteAll(['and', ['status' => Anmeldung::STATUS_UNBESTAETIGT], ['<', 'created_at', $alt]]);
        if ($n) {
            $log[] = "$n unbestätigte gelöscht";
        }

        $stunde = (int)date('G');
        if ($stunde >= $module->mailAbStunde && $stunde < $module->mailBisStunde) {
            $heute = date('Y-m-d');
            foreach (Kurs::find()->where(['status' => Kurs::STATUS_OFFEN])->andWhere(['not', ['erinnerung_tage' => null]])
                         ->andWhere(['>=', 'beginn', $heute])->all() as $kurs) {
                if (date('Y-m-d', strtotime($kurs->beginn . ' -' . $kurs->erinnerung_tage . ' days')) > $heute) {
                    continue;
                }
                $n = 0;
                foreach (Anmeldung::find()->where(['kurs_id' => $kurs->id, 'status' => Anmeldung::STATUS_PLATZ, 'erinnerung_am' => null])
                             ->andWhere(['not', ['email' => null]])->all() as $a) {
                    // Wer erst in den letzten 24 Stunden einen Platz bekommen hat, hat gerade erst alle Infos per Mail erhalten
                    if ($a->platz_am > date('Y-m-d H:i:s', strtotime('-1 day'))) {
                        $a->updateAttributes(['erinnerung_am' => date('Y-m-d H:i:s')]);
                        continue;
                    }
                    if (Dienst::mailErinnerung($a)) {
                        $a->updateAttributes(['erinnerung_am' => date('Y-m-d H:i:s')]);
                        $n++;
                    }
                }
                if ($n) {
                    $log[] = "Kurs {$kurs->id}: $n Erinnerung(en)";
                }
            }

            foreach (Anmeldung::find()->joinWith('kurs k')->where(['kurs_anmeldung.status' => Anmeldung::STATUS_PLATZ,
                'kurs_anmeldung.zahlart' => Anmeldung::ZAHLART_UEBERWEISUNG, 'kurs_anmeldung.bezahlt_am' => null,
                'kurs_anmeldung.zahlungserinnerung_am' => null, 'k.status' => Kurs::STATUS_OFFEN])
                         ->andWhere(['not', ['k.zahlungsfrist_tage' => null]])->andWhere(['not', ['kurs_anmeldung.email' => null]])
                         ->andWhere(['>', 'kurs_anmeldung.betrag', 0])->all() as $a) {
                $frist = $a->zahlungsfrist();
                if ($frist !== null && $frist < $heute && Dienst::mailZahlungserinnerung($a)) {
                    $a->updateAttributes(['zahlungserinnerung_am' => date('Y-m-d H:i:s')]);
                    $log[] = "Zahlungserinnerung {$a->getNummer()}";
                }
            }
        }

        $n = $this->anonymisieren($module->loeschenNachTagen);
        if ($n) {
            $log[] = "$n Anmeldung(en) anonymisiert";
        }

        if ($log) {
            $this->stdout(date('Y-m-d H:i') . ' ' . implode('; ', $log) . "\n");
        }
        return ExitCode::OK;
    }

    /** Personenbezogene Daten ein halbes Jahr nach Kursende löschen; Nummer, Status, Betrag und Zahlungsvermerk bleiben */
    private function anonymisieren(int $tage): int
    {
        $grenze = date('Y-m-d', strtotime("-$tage days"));
        $n = 0;
        foreach (Kurs::find()->all() as $kurs) {
            if ($kurs->getEndeDatum() >= $grenze) {
                continue;
            }
            $n += Anmeldung::updateAll([
                'kind_vorname' => '–', 'kind_nachname' => '–', 'kind_geburtsdatum' => null, 'schwimmstand' => null,
                'eltern_name' => '–', 'email' => null, 'telefon' => null, 'notfall_telefon' => null, 'notiz' => null,
                'user_id' => null, 'anonymisiert_am' => date('Y-m-d H:i:s'),
            ], ['kurs_id' => $kurs->id, 'anonymisiert_am' => null]);
        }
        return $n;
    }

    /**
     * Selbsttest der Platzvergabe mit einem unsichtbaren Testkurs (Verein nicht freigeschaltet, Mails nur als Datei
     * in @runtime/mail). Mit $behalten = 1 bleiben Kurs und Anmeldungen für Tests im Browser stehen
     * (Token werden ausgegeben); aufräumen mit kurse/selbsttest-loeschen.
     */
    public function actionSelbsttest($vereinId, $behalten = 0)
    {
        Yii::$app->mailer->useFileTransport = true;
        $fehler = 0;
        $pruefe = function (bool $ok, string $was) use (&$fehler) {
            $this->stdout(($ok ? '  ok   ' : '  FEHLER ') . $was . "\n");
            $fehler += $ok ? 0 : 1;
        };
        $kurs = new Kurs([
            'verein_id' => (int)$vereinId, 'status' => Kurs::STATUS_OFFEN, 'titel' => 'SELBSTTEST Seepferdchen', 'niveau' => 'Seepferdchen',
            'ort' => 'Hallenbad Uffenheim', 'beginn' => date('Y-m-d', strtotime('+10 days')), 'zeit_von' => '15:00', 'zeit_bis' => '15:45',
            'einheiten' => 10, 'alter_min' => 5, 'alter_max' => 8, 'plaetze' => 2, 'warteliste_max' => 1,
            'preis_mitglied' => 60, 'preis_nichtmitglied' => 90, 'zahlart_bar' => 1, 'zahlart_ueberweisung' => 1,
            'kontoinhaber' => 'Testverein', 'iban' => 'DE89 3704 0044 0532 0130 00', 'zahlungsfrist_tage' => 14, 'erinnerung_tage' => 3,
            'kontakt_email' => Yii::$app->settings->get('mailerSystemEmailAddress'),
        ]);
        if (!$kurs->save()) {
            $this->stderr(print_r($kurs->errors, true));
            return ExitCode::DATAERR;
        }
        $this->stdout("Testkurs {$kurs->id}\n");
        $neu = function (string $vorname, string $geb, bool $mitglied = false) use ($kurs) {
            $a = new Anmeldung(['kurs_id' => $kurs->id, 'scenario' => Anmeldung::SCENARIO_ELTERN]);
            $a->populateRelation('kurs', $kurs);
            $a->setAttributes(['kind_vorname' => $vorname, 'kind_nachname' => 'Test', 'kind_geburtsdatum' => $geb, 'eltern_name' => 'Eltern Test',
                'email' => 'test@example.invalid', 'telefon' => '0170 1234567', 'mitglied' => $mitglied ? 1 : 0, 'zahlart' => 'ueberweisung',
                'bedingungen_ok' => 1, 'datenschutz_ok' => 1, 'webseite' => '']);
            return $a;
        };
        $alt6 = date('Y-m-d', strtotime('-6 years'));
        $zuJung = $neu('Zujung', date('Y-m-d', strtotime('-3 years')));
        $pruefe(!$zuJung->validate() && $zuJung->hasErrors('kind_geburtsdatum'), 'Alter unter Mindestalter abgelehnt');
        $zuAlt = $neu('Zualt', date('Y-m-d', strtotime('-10 years')));
        $pruefe(!$zuAlt->validate(), 'Alter über Höchstalter abgelehnt');
        $bot = $neu('Bot', $alt6);
        $bot->webseite = 'http://spam';
        $pruefe(!$bot->validate(), 'Honeypot greift');

        $a = [];
        foreach (['Anna', 'Ben', 'Carl', 'Dora'] as $i => $n) {
            $a[$n] = $neu($n, $alt6, $i === 0);
            $pruefe(Dienst::anmelden($a[$n]), "$n angemeldet (unbestätigt)");
        }
        $pruefe($kurs->zaehle(Anmeldung::STATUS_PLATZ) === 0, 'unbestätigte belegen keinen Platz');
        // Bestätigungsreihenfolge entscheidet, nicht die Anmeldereihenfolge
        foreach (['Ben', 'Anna', 'Dora', 'Carl'] as $n) {
            Dienst::bestaetigen($a[$n]);
            $a[$n]->refresh();
        }
        $pruefe($a['Ben']->status === 'platz' && $a['Anna']->status === 'platz', 'Ben und Anna haben Platz');
        $pruefe($a['Dora']->status === 'warteliste' && $a['Dora']->getWartelistenPosition() === 1, 'Dora Warteliste Platz 1');
        $pruefe($a['Carl']->status === 'storniert' && $a['Carl']->storniert_von === 'voll', 'Carl abgewiesen (Warteliste voll)');
        $pruefe((float)$a['Anna']->betrag === 60.0 && (float)$a['Ben']->betrag === 90.0, 'Betrag Mitglied 60 / Nichtmitglied 90');
        $pruefe($a['Ben']->getVerwendungszweck() === 'K' . $kurs->id . '-' . $a['Ben']->id . ' Ben Test', 'Verwendungszweck ' . $a['Ben']->getVerwendungszweck());
        $pruefe($a['Ben']->zahlungsfrist() === date('Y-m-d', strtotime('+10 days')), 'Zahlungsfrist nicht nach Kursbeginn');

        Dienst::stornieren($a['Ben'], 'eltern');
        $a['Dora']->refresh();
        $pruefe($a['Dora']->status === 'platz', 'Dora rückt nach Abmeldung von Ben nach');
        $pruefe($kurs->zaehle('platz') === 2, 'wieder 2/2 belegt');

        $hand = new Anmeldung(['kurs_id' => $kurs->id, 'scenario' => Anmeldung::SCENARIO_HAND]);
        $hand->populateRelation('kurs', $kurs);
        $hand->setAttributes(['kind_vorname' => 'Emil', 'kind_nachname' => 'Papier', 'eltern_name' => 'Eltern Papier', 'zahlart' => 'bar']);
        $pruefe(Dienst::vonHand($hand, false) && $hand->status === 'warteliste', 'von Hand ohne Mail → Warteliste (auch über Grenze)');
        Dienst::platzGeben($hand, false);
        $pruefe($hand->status === 'platz' && $kurs->zaehle('platz') === 3, 'Platz geben über die Platzzahl hinaus');

        $kurs->plaetze = 4;
        $kurs->save();
        $pruefe(Dienst::nachruecken($kurs) === [], 'Nachrücken ohne Warteliste: nichts');

        $mails = glob(Yii::getAlias(Yii::$app->mailer->fileTransportPath) . '/*.eml');
        $this->stdout('Mails als Datei: ' . count($mails) . ' in ' . Yii::getAlias(Yii::$app->mailer->fileTransportPath) . "\n");
        $this->stdout("Token Anna: {$a['Anna']->token}\nToken Dora: {$a['Dora']->token}\nToken Carl: {$a['Carl']->token}\n");

        if (!$behalten) {
            $kurs->delete();
            $this->stdout("Testkurs gelöscht\n");
        }
        $this->stdout($fehler ? "$fehler FEHLER\n" : "Alles ok\n");
        return $fehler ? ExitCode::SOFTWARE : ExitCode::OK;
    }

    public function actionSelbsttestLoeschen()
    {
        $n = 0;
        foreach (Kurs::find()->where(['like', 'titel', 'SELBSTTEST%', false])->all() as $k) {
            $n += $k->delete() ? 1 : 0;
        }
        $this->stdout("$n Testkurs(e) gelöscht\n");
        return ExitCode::OK;
    }

    /** Einmalig: Modul im Space des Vereinsverzeichnisses aktivieren (vorher /app/yii module/enable kurse) */
    public function actionEinrichten()
    {
        $space = Yii::$app->getModule('kurse')->getSpace();
        if ($space === null) {
            $this->stderr("Space des Vereinsverzeichnisses nicht gefunden\n");
            return ExitCode::DATAERR;
        }
        if (!$space->moduleManager->isEnabled('kurse')) {
            $space->moduleManager->enable('kurse');
            $this->stdout("+ kurse im Space {$space->name}\n");
        } else {
            $this->stdout("= kurse im Space {$space->name} schon aktiv\n");
        }
        return $this->actionOeffentlich();
    }

    public function actionOeffentlich($an = null)
    {
        /** @var Module $module */
        $module = Yii::$app->getModule('kurse');
        if ($an !== null) {
            $module->settings->set('oeffentlich', (int)(bool)$an);
        }
        $this->stdout('Kursseiten öffentlich: ' . ($module->isOeffentlich() ? 'ja' : 'nein (nur Admins und Kursverwalter)') . "\n");
        return ExitCode::OK;
    }

    /**
     * Eigene Kursseite eines Vereins einrichten oder anzeigen:
     *   kurse/seite <verein_id>                         Stand anzeigen
     *   kurse/seite <verein_id> <slug> [0|1] [#farbe]   Adresse /kurse/<slug>, 0 = Vorschau, 1 = für alle
     *   kurse/seite <verein_id> -                       eigene Seite entfernen
     */
    public function actionSeite($vereinId, $slug = null, $aktiv = null, $farbe = null)
    {
        $kv = \humhub\modules\kurse\models\KursVerein::findOne((int)$vereinId);
        if (!$kv) {
            $this->stderr("Verein $vereinId ist nicht für das Kursmodul freigeschaltet.\n");
            return ExitCode::DATAERR;
        }
        if ($slug === '-') {
            $kv->slug = null;
            $kv->seite_aktiv = false;
        } elseif ($slug !== null) {
            if (!\humhub\modules\kurse\models\KursVerein::gueltigerSlug($slug)) {
                $this->stderr("Ungültige Adresse „{$slug}“ (a–z, 0–9, Bindestrich, 3–60 Zeichen; nicht: " . implode(', ', \humhub\modules\kurse\models\KursVerein::RESERVIERT) . ")\n");
                return ExitCode::DATAERR;
            }
            $kv->slug = $slug;
        }
        if ($aktiv !== null) {
            $kv->seite_aktiv = (bool)$aktiv;
        }
        if ($farbe !== null) {
            if ($farbe !== '-' && !preg_match('/^#[0-9a-fA-F]{6}$/', $farbe)) {
                $this->stderr("Farbe bitte als #rrggbb\n");
                return ExitCode::DATAERR;
            }
            $kv->farbe = $farbe === '-' ? null : strtolower($farbe);
        }
        if (!$kv->save(false)) {
            $this->stderr("Speichern fehlgeschlagen\n");
            return ExitCode::SOFTWARE;
        }
        \humhub\modules\kurse\models\KursVerein::cacheLeeren();
        if (!$kv->hatSeite()) {
            $this->stdout("Verein $vereinId: keine eigene Kursseite\n");
        } else {
            $this->stdout("Verein $vereinId: " . $kv->seiteUrl('', [], true) . "\n"
                . 'Status: ' . ($kv->seite_aktiv ? 'für alle sichtbar' : 'Vorschau (nur Admins/Kursverwalter)') . ' · Farbe ' . $kv->getFarbe() . "\n");
            if (!$kv->seite_aktiv) {
                $this->stdout('Vorschau-Link: ' . $kv->seiteUrl('', ['vorschau' => $kv->vorschauSchluessel()], true) . "\n");
            }
        }
        return ExitCode::OK;
    }
}
