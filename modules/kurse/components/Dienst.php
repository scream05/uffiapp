<?php

namespace humhub\modules\kurse\components;

use humhub\helpers\Html;
use humhub\modules\kurse\models\Anmeldung;
use humhub\modules\kurse\models\Kurs;
use humhub\widgets\mails\MailButton;
use Yii;

/**
 * Platzvergabe und Mails des Kursmoduls.
 *
 * Plätze werden nach Eingang der E-Mail-Bestätigung vergeben (nicht nach Absenden des Formulars), damit eine
 * vertippte Adresse keinen Platz blockiert. Platzvergabe und Nachrücken sperren die Kurszeile (SELECT … FOR UPDATE),
 * damit bei gleichzeitigen Bestätigungen nie mehr Plätze vergeben werden als vorhanden.
 */
class Dienst
{
    /** Neue Anmeldung aus dem Formular: speichern und Bestätigungsmail schicken */
    public static function anmelden(Anmeldung $a): bool
    {
        $a->status = Anmeldung::STATUS_UNBESTAETIGT;
        if (!Yii::$app->user->isGuest) {
            $a->user_id = Yii::$app->user->id;
        }
        if (!$a->save()) {
            return false;
        }
        self::mailBestaetigen($a);
        return true;
    }

    /** Höchstens so viele Anmeldungen je Anschluss (IP-Adresse, bei IPv6 das /64-Netz) und Stunde */
    public const MAX_PRO_STUNDE = 10;
    /** Höchstens so viele Anmeldungen je E-Mail-Adresse und Tag (jede löst eine Mail an diese Adresse aus) */
    public const MAX_PRO_ADRESSE = 4;
    /** Höchstens so viele Anmeldungen insgesamt je Stunde – darüber ist es kein normaler Betrieb mehr */
    public const MAX_GESAMT_STUNDE = 60;

    /** Anschluss für die Zählung: IPv4-Adresse bzw. bei IPv6 das /64-Netz (ein Anschluss hat dort beliebig viele Adressen) */
    private static function anschluss(): string
    {
        // Konsole (Selbsttest) hat keine IP-Adresse
        $ip = Yii::$app->request instanceof \yii\web\Request ? (string)Yii::$app->request->userIP : 'konsole';
        if (str_contains($ip, ':') && ($bin = @inet_pton($ip)) !== false) {
            return bin2hex(substr($bin, 0, 8)) . '::/64';
        }
        return $ip;
    }

    /** Zähler im Cache um eins erhöhen; false, wenn die Grenze schon erreicht war (dann wird nicht gezählt) */
    private static function zaehlen(string $key, int $max, int $sekunden): bool
    {
        $n = (int)Yii::$app->cache->get($key);
        if ($n >= $max) {
            return false;
        }
        Yii::$app->cache->set($key, $n + 1, $sekunden);
        return true;
    }

    /** Gleiches Kind im selben Kurs (nicht abgemeldet), neueste zuerst */
    private static function gleichesKind(Anmeldung $a): array
    {
        return Anmeldung::find()->where(['kurs_id' => $a->kurs_id, 'kind_geburtsdatum' => $a->kind_geburtsdatum])
            ->andWhere(['kind_vorname' => $a->kind_vorname, 'kind_nachname' => $a->kind_nachname])
            ->andWhere(['!=', 'status', Anmeldung::STATUS_STORNIERT])->orderBy(['id' => SORT_DESC])->all();
    }

    /**
     * Anmeldeformular der Eltern (Space-Seite und eigene Vereinsseite): Sperren gegen Massenanmeldungen, Prüfung,
     * Doppelt-Prüfung, Speichern und Bestätigungsmail. Bei false stehen die Fehler im Modell oder in $hinweis.
     *
     * Bei true zeigt die Seite immer dasselbe („Wir haben dir eine Mail geschickt“) – auch wenn das Kind schon
     * angemeldet ist. Sonst könnte jeder am Formular ablesen, ob ein bestimmtes Kind im Kurs ist. Was wirklich
     * passiert ist, steht nur in der Mail an die jeweilige Adresse.
     */
    public static function formularAnmelden(Anmeldung $a, ?string &$hinweis = null): bool
    {
        if (!$a->validate()) {
            return false;
        }
        // Erst zählen, dann prüfen: auch Versuche mit schon angemeldeten Kindern zählen mit
        $std = date('YmdH');
        if (!self::zaehlen('kurse-anmeldung-ip-' . md5(self::anschluss()), self::MAX_PRO_STUNDE, 3600)) {
            $hinweis = 'Zu viele Anmeldungen in kurzer Zeit. Bitte versuche es später noch einmal.';
            return false;
        }
        if (!self::zaehlen('kurse-anmeldung-adr-' . md5(mb_strtolower((string)$a->email)), self::MAX_PRO_ADRESSE, 86400)) {
            $hinweis = 'Für diese E-Mail-Adresse wurden heute schon mehrere Anmeldungen abgeschickt. Bitte schau in dein Postfach (auch im Spam-Ordner) '
                . 'oder melde dich direkt beim Verein: ' . $a->kurs->kontakt_email;
            return false;
        }
        if (!self::zaehlen("kurse-anmeldung-gesamt-$std", self::MAX_GESAMT_STUNDE, 3700)) {
            if (Yii::$app->cache->add("kurse-anmeldung-gesamt-warnung-$std", 1, 3700)) {
                Yii::warning('Kursanmeldung: mehr als ' . self::MAX_GESAMT_STUNDE . ' Anmeldungen in einer Stunde – weitere werden bis zur vollen Stunde abgewiesen', 'kurse');
            }
            $hinweis = 'Gerade gehen ungewöhnlich viele Anmeldungen ein. Bitte versuche es in einer Stunde noch einmal oder melde dich direkt beim Verein: '
                . $a->kurs->kontakt_email;
            return false;
        }

        $gleicheAdresse = fn(Anmeldung $d) => mb_strtolower((string)$d->email) === mb_strtolower((string)$a->email);
        $vorhanden = self::gleichesKind($a);
        $bestaetigt = array_values(array_filter($vorhanden, fn(Anmeldung $d) => $d->status !== Anmeldung::STATUS_UNBESTAETIGT));
        if ($bestaetigt) {
            // Kind ist schon angemeldet: nichts Neues anlegen. Dieselbe Adresse bekommt ihren Link noch einmal,
            // bei einer anderen Adresse erfährt nur die ursprüngliche davon (höchstens einmal am Tag).
            $d = $bestaetigt[0];
            try {
                if ($gleicheAdresse($d)) {
                    self::mailSchonAngemeldet($d);
                } elseif ($d->email && Yii::$app->cache->add('kurse-doppelt-hinweis-' . $d->id, 1, 86400)) {
                    self::mailWeitererVersuch($d);
                }
            } catch (\Throwable $e) {
                Yii::error($e, 'kurse');
            }
            return true;
        }
        foreach ($vorhanden as $d) {
            // Neuer Versuch mit derselben Adresse (z. B. weil die erste Mail nicht ankam): alten Versuch ersetzen.
            // Unbestätigte Anmeldungen mit ANDERER Adresse bleiben stehen – wer zuerst bestätigt, bekommt den Platz.
            if ($gleicheAdresse($d)) {
                $d->delete();
            }
        }
        try {
            if (self::anmelden($a)) {
                return true;
            }
        } catch (\Throwable $e) {
            // Ohne Bestätigungsmail ist die Anmeldung wertlos: nicht halb gespeichert liegen lassen
            Yii::error($e, 'kurse');
            if (!$a->isNewRecord) {
                $a->delete();
            }
            $a->setIsNewRecord(true);
            $a->id = null;
            $hinweis = 'Die Anmeldung konnte gerade nicht gespeichert werden (technischer Fehler). Bitte versuche es in ein paar Minuten noch einmal.';
        }
        return false;
    }

    /** Von Hand eingetragen (Kursverwalter): sofort Platz bzw. Warteliste, Mail nur wenn Adresse vorhanden */
    public static function vonHand(Anmeldung $a, bool $mail): bool
    {
        $a->von_hand = true;
        $a->status = Anmeldung::STATUS_UNBESTAETIGT;
        if (!$a->save()) {
            return false;
        }
        self::einordnen($a, $mail && $a->email, true);
        return true;
    }

    /**
     * E-Mail bestätigt: Platz oder Warteliste. Gibt false zurück, wenn Kurs und Warteliste voll sind
     * (die Anmeldung wird dann storniert).
     */
    public static function bestaetigen(Anmeldung $a): bool
    {
        if ($a->status !== Anmeldung::STATUS_UNBESTAETIGT) {
            return $a->status !== Anmeldung::STATUS_STORNIERT;
        }
        foreach (self::gleichesKind($a) as $d) {
            if ($d->id !== $a->id && $d->status !== Anmeldung::STATUS_UNBESTAETIGT) {
                // Für dieses Kind wurde inzwischen eine andere Anmeldung bestätigt: diese hier zählt nicht
                $a->status = Anmeldung::STATUS_STORNIERT;
                $a->storniert_am = date('Y-m-d H:i:s');
                $a->storniert_von = 'doppelt';
                $a->save(false);
                return false;
            }
        }
        $ok = self::einordnen($a, true, false);
        if ($ok) {
            // Übrige unbestätigte Versuche für dasselbe Kind sind damit erledigt
            foreach (self::gleichesKind($a) as $d) {
                if ($d->id !== $a->id && $d->status === Anmeldung::STATUS_UNBESTAETIGT) {
                    $d->delete();
                }
            }
        }
        return $ok;
    }

    private static function einordnen(Anmeldung $a, bool $mail, bool $trotzdemWarteliste): bool
    {
        $tx = Yii::$app->db->beginTransaction();
        try {
            $kurs = self::sperren($a->kurs_id);
            $a->bestaetigt_am = date('Y-m-d H:i:s');
            if ($kurs->status === Kurs::STATUS_OFFEN && $kurs->freiePlaetze() > 0
                && !Anmeldung::find()->where(['kurs_id' => $kurs->id, 'status' => Anmeldung::STATUS_WARTELISTE])->exists()) {
                self::platzSetzen($a, $kurs);
            } elseif ($kurs->status !== Kurs::STATUS_ABGESAGT && ($trotzdemWarteliste || $kurs->wartelisteFrei())) {
                $a->status = Anmeldung::STATUS_WARTELISTE;
            } else {
                $a->status = Anmeldung::STATUS_STORNIERT;
                $a->storniert_am = date('Y-m-d H:i:s');
                $a->storniert_von = 'voll';
            }
            $a->save(false);
            $tx->commit();
        } catch (\Throwable $e) {
            $tx->rollBack();
            throw $e;
        }
        if ($mail) {
            if ($a->status === Anmeldung::STATUS_PLATZ) {
                self::mailZusage($a, false);
            } elseif ($a->status === Anmeldung::STATUS_WARTELISTE) {
                self::mailWarteliste($a);
            } else {
                self::mailVoll($a);
            }
        }
        return $a->status !== Anmeldung::STATUS_STORNIERT;
    }

    private static function sperren(int $kursId): Kurs
    {
        Yii::$app->db->createCommand('SELECT id FROM kurs WHERE id = :id FOR UPDATE', [':id' => $kursId])->execute();
        return Kurs::findOne($kursId);
    }

    private static function platzSetzen(Anmeldung $a, Kurs $kurs): void
    {
        $a->status = Anmeldung::STATUS_PLATZ;
        $a->platz_am = date('Y-m-d H:i:s');
        if ($a->betrag === null && $kurs->hatPreis()) {
            $a->betrag = $kurs->preisFuer((bool)$a->mitglied);
        }
    }

    /** Kursverwalter gibt jemandem von der Warteliste (oder Unbestätigten) direkt einen Platz – auch über die Platzzahl hinaus */
    public static function platzGeben(Anmeldung $a, bool $mail = true): void
    {
        if (!in_array($a->status, [Anmeldung::STATUS_WARTELISTE, Anmeldung::STATUS_UNBESTAETIGT, Anmeldung::STATUS_STORNIERT], true)) {
            return;
        }
        $a->bestaetigt_am = $a->bestaetigt_am ?: date('Y-m-d H:i:s');
        $a->storniert_am = null;
        $a->storniert_von = null;
        self::platzSetzen($a, $a->kurs);
        $a->save(false);
        if ($mail && $a->email) {
            self::mailZusage($a, true);
        }
    }

    /** Abmelden (durch Eltern oder Verein); frei gewordener Platz geht an die Warteliste */
    public static function stornieren(Anmeldung $a, string $von, bool $mail = true): void
    {
        if ($a->status === Anmeldung::STATUS_STORNIERT) {
            return;
        }
        $hatteplatz = $a->status === Anmeldung::STATUS_PLATZ;
        $a->status = Anmeldung::STATUS_STORNIERT;
        $a->storniert_am = date('Y-m-d H:i:s');
        $a->storniert_von = $von;
        $a->save(false);
        if ($mail && $a->email && $a->bestaetigt_am) {
            self::mailAbgemeldet($a, $von);
        }
        if ($hatteplatz) {
            self::nachruecken($a->kurs);
        }
    }

    /**
     * Freie Plätze an die Warteliste vergeben (in Reihenfolge der Bestätigung). Nur vor Kursbeginn und bei offenem Kurs;
     * danach entscheidet der Verein von Hand. @return Anmeldung[] nachgerückt
     */
    public static function nachruecken(Kurs $kurs): array
    {
        if ($kurs->status !== Kurs::STATUS_OFFEN || $kurs->hatBegonnen()) {
            return [];
        }
        $neu = [];
        $tx = Yii::$app->db->beginTransaction();
        try {
            $kurs = self::sperren($kurs->id);
            $frei = $kurs->freiePlaetze();
            if ($frei > 0) {
                foreach (Anmeldung::find()->where(['kurs_id' => $kurs->id, 'status' => Anmeldung::STATUS_WARTELISTE])
                             ->orderBy(['bestaetigt_am' => SORT_ASC, 'id' => SORT_ASC])->limit($frei)->all() as $a) {
                    self::platzSetzen($a, $kurs);
                    $a->save(false);
                    $neu[] = $a;
                }
            }
            $tx->commit();
        } catch (\Throwable $e) {
            $tx->rollBack();
            throw $e;
        }
        foreach ($neu as $a) {
            if ($a->email) {
                self::mailZusage($a, true);
            }
        }
        return $neu;
    }

    /** Kurs absagen: alle mit Platz oder auf der Warteliste werden informiert */
    public static function absagen(Kurs $kurs, string $grund): int
    {
        $kurs->status = Kurs::STATUS_ABGESAGT;
        $kurs->absage_grund = $grund !== '' ? $grund : null;
        $kurs->save(false);
        $n = 0;
        foreach (Anmeldung::find()->where(['kurs_id' => $kurs->id, 'status' => [Anmeldung::STATUS_PLATZ, Anmeldung::STATUS_WARTELISTE, Anmeldung::STATUS_UNBESTAETIGT]])->all() as $a) {
            $warBestaetigt = $a->status !== Anmeldung::STATUS_UNBESTAETIGT;
            $a->status = Anmeldung::STATUS_STORNIERT;
            $a->storniert_am = date('Y-m-d H:i:s');
            $a->storniert_von = 'absage';
            $a->save(false);
            if ($warBestaetigt && $a->email && self::mailAbsage($a, $grund)) {
                $n++;
            }
        }
        return $n;
    }

    // ---------------------------------------------------------------- Mails

    private static function kursBlock(Kurs $k): string
    {
        $zeilen = [
            'Kurs: ' . $k->titel . ($k->niveau ? ' (' . $k->niveau . ')' : ''),
            'Verein: ' . $k->verein->name,
            'Termine: ' . $k->getTermineText(),
        ];
        if ($k->termine_hinweis) {
            $zeilen[] = '          ' . $k->termine_hinweis;
        }
        $zeilen[] = 'Ort: ' . $k->ort;
        return implode("\n", $zeilen);
    }

    private static function zahlungBlock(Anmeldung $a): string
    {
        $k = $a->kurs;
        if (!$k->hatPreis() || $a->betrag === null || (float)$a->betrag <= 0) {
            return '';
        }
        if ($a->bezahlt_am) {
            return 'Kursgebühr: ' . Kurs::euro($a->betrag) . ' – bereits bezahlt, danke!';
        }
        if ($a->zahlart === Anmeldung::ZAHLART_UEBERWEISUNG) {
            $frist = $a->zahlungsfrist();
            return "Bitte überweise die Kursgebühr" . ($frist ? ' bis ' . date('d.m.Y', strtotime($frist)) : ' vor dem ersten Kurstag') . ":\n"
                . 'Betrag: ' . Kurs::euro($a->betrag) . "\n"
                . 'Empfänger: ' . $k->kontoinhaber . "\n"
                . 'IBAN: ' . trim(chunk_split((string)$k->iban, 4, ' ')) . "\n"
                . 'Verwendungszweck: ' . $a->getVerwendungszweck();
        }
        return 'Bitte bring die Kursgebühr von ' . Kurs::euro($a->betrag) . ' am ersten Kurstag bar mit (am besten passend).';
    }

    private static function anrede(Anmeldung $a): string
    {
        return 'Hallo ' . $a->eltern_name . ',';
    }

    private static function fuss(Anmeldung $a): string
    {
        return "Deine Anmeldung ansehen oder abmelden:\n" . $a->getStatusUrl()
            . "\n\nFragen? Antworte einfach auf diese Mail, sie geht an " . $a->kurs->kontakt_email . '.'
            . "\n\nViele Grüße\n" . $a->kurs->verein->name;
    }

    public static function mailBestaetigen(Anmeldung $a): bool
    {
        $link = $a->kurs->elternUrl('bestaetigen', ['t' => $a->token], true);
        $text = self::anrede($a) . "\n\n"
            . 'du hast ' . $a->getKindName() . ' für folgenden Kurs angemeldet:' . "\n\n" . self::kursBlock($a->kurs) . "\n\n"
            . 'Bitte bestätige die Anmeldung über diesen Link. Erst dann wird ein Platz (oder ein Platz auf der Warteliste) vergeben – '
            . 'in der Reihenfolge der Bestätigungen.' . "\n\n" . $link . "\n\n"
            . 'Der Link gilt ' . Yii::$app->getModule('kurse')->unbestaetigtStunden . ' Stunden. '
            . 'Wenn du das nicht warst, kannst du diese Mail einfach ignorieren.' . "\n\n"
            . 'Viele Grüße' . "\n" . $a->kurs->verein->name;
        return self::senden($a, 'Bitte bestätige die Anmeldung: ' . $a->kurs->titel, $text, [$link, 'Anmeldung bestätigen']);
    }

    /** Erneute Anmeldung mit derselben Adresse, obwohl das Kind schon angemeldet ist: Link noch einmal schicken */
    public static function mailSchonAngemeldet(Anmeldung $a): bool
    {
        $text = self::anrede($a) . "\n\n"
            . $a->getKindName() . ' ist für diesen Kurs bereits angemeldet (' . $a->getStatusText() . '):' . "\n\n" . self::kursBlock($a->kurs) . "\n\n"
            . 'Du musst nichts weiter tun – eine zweite Anmeldung haben wir nicht angelegt.' . "\n\n" . self::fuss($a);
        return self::senden($a, 'Schon angemeldet: ' . $a->kurs->titel, $text);
    }

    /** Jemand hat dasselbe Kind mit einer anderen E-Mail-Adresse angemeldet: nur die ursprüngliche Adresse erfährt davon */
    public static function mailWeitererVersuch(Anmeldung $a): bool
    {
        $text = self::anrede($a) . "\n\n"
            . 'für ' . $a->getKindName() . ' wurde gerade noch einmal eine Anmeldung zu diesem Kurs abgeschickt, mit einer anderen E-Mail-Adresse:' . "\n\n"
            . self::kursBlock($a->kurs) . "\n\n"
            . 'Deine Anmeldung besteht unverändert (' . $a->getStatusText() . '), eine zweite haben wir nicht angelegt. '
            . 'War das zum Beispiel der andere Elternteil, ist nichts weiter zu tun. Kommt dir das merkwürdig vor, antworte bitte auf diese Mail.'
            . "\n\n" . self::fuss($a);
        return self::senden($a, 'Weitere Anmeldung für ' . $a->kind_vorname . ': ' . $a->kurs->titel, $text);
    }

    public static function mailZusage(Anmeldung $a, bool $nachgerueckt): bool
    {
        $text = self::anrede($a) . "\n\n"
            . ($nachgerueckt ? 'gute Nachricht: Es ist ein Platz frei geworden. ' . $a->getKindName() . ' ist von der Warteliste nachgerückt und hat jetzt einen festen Platz im Kurs.'
                : 'die Anmeldung ist bestätigt: ' . $a->getKindName() . ' hat einen Platz im Kurs.') . "\n\n"
            . self::kursBlock($a->kurs) . "\n\n"
            . 'Anmeldenummer: ' . $a->getNummer() . "\n";
        if ($z = self::zahlungBlock($a)) {
            $text .= "\n" . $z . "\n";
        }
        if (!$a->kurs->hatBegonnen() && $a->kurs->erinnerung_tage) {
            $text .= "\nKurz vor dem Start erinnern wir dich noch einmal per Mail.\n";
        }
        $text .= "\nFalls ihr doch nicht teilnehmen könnt, melde dich bitte ab – dann rückt das nächste Kind nach.\n\n" . self::fuss($a);
        return self::senden($a, ($nachgerueckt ? 'Nachgerückt: ' : 'Platz bestätigt: ') . $a->kurs->titel, $text,
            [$a->getStatusUrl(), 'Anmeldung ansehen']);
    }

    public static function mailWarteliste(Anmeldung $a): bool
    {
        $text = self::anrede($a) . "\n\n"
            . 'danke für die Bestätigung. Der Kurs ist leider schon voll – ' . $a->getKindName()
            . ' steht auf der Warteliste (Platz ' . $a->getWartelistenPosition() . ').' . "\n\n"
            . self::kursBlock($a->kurs) . "\n\n"
            . 'Wird ein Platz frei, rückt das nächste Kind automatisch nach und bekommt eine Mail. Du musst nichts weiter tun.' . "\n\n"
            . self::fuss($a);
        return self::senden($a, 'Warteliste: ' . $a->kurs->titel, $text, [$a->getStatusUrl(), 'Anmeldung ansehen']);
    }

    public static function mailVoll(Anmeldung $a): bool
    {
        $text = self::anrede($a) . "\n\n"
            . 'danke für die Bestätigung. Leider sind der Kurs „' . $a->kurs->titel . '“ und die Warteliste inzwischen voll, '
            . 'deshalb konnten wir ' . $a->getKindName() . ' nicht mehr aufnehmen.' . "\n\n"
            . 'Bei Fragen antworte einfach auf diese Mail.' . "\n\n" . 'Viele Grüße' . "\n" . $a->kurs->verein->name;
        return self::senden($a, 'Kurs ausgebucht: ' . $a->kurs->titel, $text);
    }

    public static function mailAbgemeldet(Anmeldung $a, string $von): bool
    {
        $text = self::anrede($a) . "\n\n"
            . ($von === 'eltern' ? 'du hast ' . $a->getKindName() . ' vom Kurs „' . $a->kurs->titel . '“ abgemeldet.'
                : $a->getKindName() . ' wurde vom Verein vom Kurs „' . $a->kurs->titel . '“ abgemeldet.') . "\n\n"
            . ($a->bezahlt_am ? "Wegen der bereits bezahlten Kursgebühr melden wir uns bei dir.\n\n" : '')
            . 'Bei Fragen antworte einfach auf diese Mail.' . "\n\n" . 'Viele Grüße' . "\n" . $a->kurs->verein->name;
        return self::senden($a, 'Abmeldung: ' . $a->kurs->titel, $text);
    }

    public static function mailAbsage(Anmeldung $a, string $grund): bool
    {
        $text = self::anrede($a) . "\n\n"
            . 'leider müssen wir den Kurs „' . $a->kurs->titel . '“ (Beginn ' . date('d.m.Y', strtotime($a->kurs->beginn)) . ') absagen.' . "\n\n"
            . ($grund !== '' ? $grund . "\n\n" : '')
            . ($a->bezahlt_am ? "Die bereits bezahlte Kursgebühr bekommst du zurück – wir melden uns dazu bei dir.\n\n" : '')
            . 'Bei Fragen antworte einfach auf diese Mail.' . "\n\n" . 'Viele Grüße' . "\n" . $a->kurs->verein->name;
        return self::senden($a, 'Kurs abgesagt: ' . $a->kurs->titel, $text);
    }

    public static function mailErinnerung(Anmeldung $a): bool
    {
        $k = $a->kurs;
        $tage = (int)round((strtotime($k->beginn) - strtotime(date('Y-m-d'))) / 86400);
        $wann = $tage <= 0 ? 'heute' : ($tage === 1 ? 'morgen' : "in $tage Tagen");
        $text = self::anrede($a) . "\n\n"
            . 'kleine Erinnerung: ' . $wann . ' beginnt der Kurs von ' . $a->getKindName() . '.' . "\n\n"
            . self::kursBlock($k) . "\n";
        if ($z = self::zahlungBlock($a)) {
            $text .= "\n" . $z . "\n";
        }
        $text .= "\nWir freuen uns auf euch! Falls ihr doch nicht kommen könnt, melde dich bitte ab.\n\n" . self::fuss($a);
        return self::senden($a, 'Erinnerung: ' . $k->titel . ' beginnt ' . $wann, $text, [$a->getStatusUrl(), 'Anmeldung ansehen']);
    }

    public static function mailZahlungserinnerung(Anmeldung $a): bool
    {
        $text = self::anrede($a) . "\n\n"
            . 'für den Kurs von ' . $a->getKindName() . ' haben wir noch keine Zahlung verbucht. '
            . 'Falls du schon überwiesen hast, ist alles gut – dann haben sich unsere Nachrichten überschnitten.' . "\n\n"
            . self::zahlungBlock($a) . "\n\n" . self::fuss($a);
        return self::senden($a, 'Zahlungserinnerung: ' . $a->kurs->titel, $text, [$a->getStatusUrl(), 'Anmeldung ansehen']);
    }

    /** Rundmail des Kursverwalters (Text vom Verein, darunter der persönliche Link) */
    public static function mailRundmail(Anmeldung $a, string $betreff, string $nachricht): bool
    {
        $text = self::anrede($a) . "\n\n" . $nachricht . "\n\n"
            . '(Diese Nachricht betrifft den Kurs „' . $a->kurs->titel . '“ von ' . $a->getKindName() . '.)' . "\n\n" . self::fuss($a);
        return self::senden($a, $betreff, $text);
    }

    /**
     * Mail im Stil der App (HumHub-Layout, Knopf über designwahl). Absendername: Verein über die Plattform,
     * Antworten gehen an die Kontakt-E-Mail des Kurses.
     */
    public static function senden(Anmeldung $a, string $betreff, string $text, ?array $knopf = null): bool
    {
        if (!$a->email) {
            return false;
        }
        try {
            $html = nl2br(Html::encode($text), false);
            $html = preg_replace('#https?://[^\s<]+#', '<a href="$0">$0</a>', $html);
            if ($knopf) {
                // MailButton liefert nur ein <td> – eigene Tabelle drumherum, sonst steht der Knopf als Spalte neben dem Text
                $html .= '<table role="presentation" width="100%" border="0" cellspacing="0" cellpadding="0" style="margin-top:20px"><tr>'
                    . '<td align="center"><table role="presentation" border="0" cellspacing="0" cellpadding="0" align="center"><tr>'
                    . MailButton::widget(['url' => $knopf[0], 'text' => $knopf[1]])
                    . '</tr></table></td></tr></table>';
            }
            $mailer = Yii::$app->mailer;
            $html = $mailer->render('@humhub/views/mail/TextOnly', ['message' => $html], $mailer->htmlLayout);
            $absender = Yii::$app->settings->get('mailerSystemEmailAddress');
            $name = Yii::$app->settings->get('mailerSystemEmailName');
            $mail = $mailer->compose()
                ->setFrom([$absender => mb_substr($a->kurs->verein->name . ' über ' . $name, 0, 120)])
                ->setTo($a->email)
                ->setReplyTo($a->kurs->kontakt_email)
                ->setSubject($betreff);
            $mail->getSymfonyEmail()->text($text)->html($html);
            $ok = $mail->send();
        } catch (\Throwable $e) {
            Yii::error($e, 'kurse');
            $ok = false;
        }
        if (!$ok) {
            Yii::warning("Kurs-Mail „{$betreff}“ an Anmeldung {$a->id} nicht versendet", 'kurse');
        }
        return $ok;
    }
}
