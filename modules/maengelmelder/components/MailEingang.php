<?php

namespace humhub\modules\maengelmelder\components;

use humhub\modules\content\models\Content;
use humhub\modules\maengelmelder\jobs\BuergerJob;
use humhub\modules\maengelmelder\models\BauhofNachricht;
use humhub\modules\maengelmelder\Module;
use Yii;
use yii\web\UploadedFile;

/**
 * Antworten der meldenden Person per E-Mail. Mails an sie (Eingangsbestätigung, Statusinfos, Nachrichten des
 * Bauhofs) haben als Antwortadresse „smartcity+m<Nr>-<Prüfcode>@…“ (Plus-Adresse, landet im Postfach von
 * kontaktMail). `yii bauhof/mail-abrufen` holt ungelesene Mails der letzten 3 Tage per IMAP, nimmt die an diese Adressen (Zugang wie der
 * Mailversand: Benutzer/Passwort aus den HumHub-Mail-Einstellungen), legt Text und Fotos im Verlauf der Meldung ab
 * (bauhof_nachricht, buerger = 1, typ mail) und reicht sie an BuergerJob weiter (Glocke an den Bauhof, Weiterleitung
 * an den zugewiesenen Mitarbeiter). Zitierte alte Mail, Signatur und Abwesenheitsnotizen werden verworfen.
 */
class MailEingang
{
    public const IMAP_HOST = 'imap.gmail.com';
    public const IMAP_PORT = 993;
    private const MAX_FOTOS = 3;
    /** Gmail-Label für verarbeitete Antworten; nach LOESCHEN_TAGE kommen sie in den Papierkorb */
    public const LABEL = 'Maengelmelder';
    public const LOESCHEN_TAGE = 30;
    private const MAX_TEXT = 3000;

    private static function module(): Module
    {
        return Yii::$app->getModule('maengelmelder');
    }

    // ------------------------------------------------------------------ Antwortadresse

    /** Antwortadresse für Mails zu einer Meldung, z. B. smartcity+m123-a1b2c3d4e5@buergerliste-uffenheim.de */
    public static function antwortAdresse(int $contentId): string
    {
        [$lokal, $domain] = explode('@', self::module()->kontaktMail, 2);
        return "$lokal+m$contentId-" . self::pruefcode($contentId) . "@$domain";
    }

    /** Content-ID aus einer Antwortadresse, wenn der Prüfcode stimmt */
    public static function contentIdAus(string $adresse): ?int
    {
        if (preg_match('/\+m(\d+)-([0-9a-f]{10})@/i', $adresse, $m) && hash_equals(self::pruefcode((int)$m[1]), strtolower($m[2]))) {
            return (int)$m[1];
        }
        return null;
    }

    private static function pruefcode(int $contentId): string
    {
        $settings = self::module()->settings;
        $geheim = $settings->get('mailGeheimnis');
        if (!$geheim) {
            $geheim = bin2hex(random_bytes(24));
            $settings->set('mailGeheimnis', $geheim);
        }
        return substr(hash_hmac('sha256', "mangel-$contentId", $geheim), 0, 10);
    }

    // ------------------------------------------------------------------ Abruf

    /**
     * Ungelesene Antworten abholen und verarbeiten.
     * @return string[] Protokollzeilen
     */
    public static function abrufen(bool $probelauf = false): array
    {
        $log = [];
        $imap = new Imap(self::IMAP_HOST, self::IMAP_PORT);
        $imap->login((string)Yii::$app->settings->get('mailerUsername'), (string)Yii::$app->settings->get('mailerPassword'));
        try {
            // Gmails IMAP-Suche findet Teile von Plus-Adressen nicht → Kopfzeilen der Mails der letzten 3 Tage selbst
            // prüfen. Nicht auf „ungelesen“ verlassen (jemand könnte das Postfach vorher öffnen): erledigte UIDs merken.
            $auswahl = $imap->befehl('SELECT INBOX');
            $gueltig = preg_match('/UIDVALIDITY (\d+)/', $auswahl, $m) ? $m[1] : '0';
            $erledigt = json_decode((string)self::module()->settings->get('mailErledigt', '{}'), true) ?: [];
            if (($erledigt['v'] ?? null) !== $gueltig) {
                $erledigt = ['v' => $gueltig, 'uids' => []];
            }
            $seit = date('d-M-Y', strtotime('-3 days'));
            foreach ($imap->suche("SINCE $seit") as $uid) {
                if (in_array($uid, $erledigt['uids'], true) || !preg_match('/\+m\d+-[0-9a-f]{10}@/i', $imap->kopfzeilen($uid))) {
                    continue;
                }
                try {
                    $log[] = "UID $uid: " . self::verarbeiten($imap->hole($uid), $probelauf);
                } catch (\Throwable $e) {
                    $log[] = "UID $uid: Fehler " . $e->getMessage();
                    Yii::error("Mail-Eingang UID $uid: " . $e->getMessage(), 'maengelmelder');
                    continue; // beim nächsten Lauf erneut versuchen
                }
                if (!$probelauf) {
                    $erledigt['uids'][] = $uid;
                    $imap->befehl("UID STORE $uid +FLAGS (\\Seen)");
                    try {
                        $imap->befehl("UID STORE $uid +X-GM-LABELS (" . self::LABEL . ")"); // für das Aufräumen (nur Gmail)
                    } catch (\Throwable $e) {
                    }
                }
            }
            if (!$probelauf) {
                $erledigt['uids'] = array_slice($erledigt['uids'], -500);
                self::module()->settings->set('mailErledigt', json_encode($erledigt));
            }
            // Einmal täglich: verarbeitete Antworten nach LOESCHEN_TAGE in den Papierkorb (Gmail leert ihn nach 30 Tagen)
            if (!$probelauf && self::module()->settings->get('mailAufgeraeumt') !== date('Y-m-d')) {
                $n = self::aufraeumen($imap);
                self::module()->settings->set('mailAufgeraeumt', date('Y-m-d'));
                if ($n) {
                    $log[] = "$n verarbeitete Antworten älter als " . self::LOESCHEN_TAGE . ' Tage in den Papierkorb verschoben';
                }
            }
        } finally {
            $imap->logout();
        }
        return $log;
    }

    /** Verarbeitete Antworten (Label) älter als LOESCHEN_TAGE in den Papierkorb; gibt die Anzahl zurück */
    private static function aufraeumen(Imap $imap): int
    {
        $papierkorb = $imap->ordnerMit('\\Trash');
        if ($papierkorb === null) {
            Yii::warning('Mail-Eingang: Papierkorb im Postfach nicht gefunden – alte Antworten bleiben liegen', 'maengelmelder');
            return 0;
        }
        $uids = $imap->suche('X-GM-RAW "label:' . self::LABEL . ' older_than:' . self::LOESCHEN_TAGE . 'd"');
        foreach (array_chunk($uids, 100) as $teil) {
            $imap->befehl('UID MOVE ' . implode(',', $teil) . ' "' . addcslashes($papierkorb, '"\\') . '"');
        }
        return count($uids);
    }

    /** Eine Mail (roh) verarbeiten; gibt eine Protokollzeile zurück */
    public static function verarbeiten(string $roh, bool $probelauf = false): string
    {
        $mail = Mime::parse($roh);
        $kopf = $mail['kopf'];
        $contentId = null;
        foreach (['delivered-to', 'to', 'cc', 'x-original-to'] as $feld) {
            foreach ((array)($kopf[$feld] ?? []) as $wert) {
                if (preg_match_all('/[^\s<>,"]+@[^\s<>,"]+/', $wert, $m)) {
                    foreach ($m[0] as $adresse) {
                        $contentId ??= self::contentIdAus($adresse);
                    }
                }
            }
        }
        if (!$contentId) {
            return 'keine gültige Antwortadresse – übersprungen';
        }
        if (self::automatisch($kopf)) {
            return "Meldung $contentId: automatische Antwort (Abwesenheit o. Ä.) – verworfen";
        }
        $content = Content::findOne($contentId);
        if (!$content || !in_array((int)$content->state, [Content::STATE_PUBLISHED, Content::STATE_DRAFT], true)) {
            return "Meldung $contentId gibt es nicht mehr – verworfen";
        }
        $messageId = 'mail:' . mb_substr(trim((string)($kopf['message-id'][0] ?? sha1($roh)), '<> '), 0, 180);
        if (BauhofNachricht::find()->where(['wamid' => $messageId])->exists()) {
            return "Meldung $contentId: schon übernommen";
        }
        $text = self::ohneZitat($mail['text']);
        $fotos = array_slice(array_values(array_filter($mail['anhaenge'], fn($a) => str_starts_with($a['typ'], 'image/'))), 0, self::MAX_FOTOS);
        if ($text === '' && !$fotos) {
            return "Meldung $contentId: leere Antwort – verworfen";
        }
        $absender = mb_substr(trim((string)($kopf['from'][0] ?? '')), 0, 150);
        if ($probelauf) {
            return "Meldung $contentId: würde übernehmen (" . mb_strlen($text) . ' Zeichen, ' . count($fotos) . " Foto(s)) von $absender: "
                . mb_strimwidth(preg_replace('/\s+/u', ' ', $text), 0, 120, '…');
        }

        // STOPP per Mail: keine Mail-Infos mehr
        if (in_array(mb_strtolower(trim($text, " \t\n\r!.?")), ['stop', 'stopp', 'abmelden', 'abbestellen', 'keine infos', 'keine infos mehr'], true)) {
            \humhub\modules\maengelmelder\models\MaengelKontakt::updateAll(['info_mail' => 0], ['content_id' => $contentId]);
            Bauhof::vermerk($contentId, null, null, 'Meldende Person hat die E-Mail-Infos abbestellt (STOPP)');
            return "Meldung $contentId: E-Mail-Infos abbestellt";
        }

        $erste = null;
        $dateien = [];
        foreach ($fotos as $i => $a) {
            $tmp = tempnam(sys_get_temp_dir(), 'mmail');
            file_put_contents($tmp, $a['daten']);
            $dateien[] = new UploadedFile(['name' => $a['name'] ?: "foto-$i.jpg", 'tempName' => $tmp, 'type' => $a['typ'], 'size' => strlen($a['daten']), 'error' => UPLOAD_ERR_OK]);
        }
        [$files] = $dateien ? GastFotos::speichern($dateien) : [[]];
        foreach ($dateien as $d) {
            @unlink($d->tempName);
        }
        // Text mit dem ersten Foto, weitere Fotos als eigene Einträge (wie bei WhatsApp)
        $teile = $files ?: [null];
        foreach ($teile as $i => $file) {
            $n = new BauhofNachricht([
                'content_id' => $contentId,
                'richtung' => BauhofNachricht::EIN,
                'buerger' => 1,
                'typ' => 'mail',
                'text' => $i === 0 ? ($text !== '' ? mb_substr($text, 0, self::MAX_TEXT) : null) : null,
                'wamid' => $i === 0 ? $messageId : mb_substr("$messageId#$i", 0, 190),
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            $n->save(false);
            if ($file) {
                $file->object_model = BauhofNachricht::class;
                $file->object_id = $n->id;
                $file->save(false);
                $n->updateAttributes(['file_id' => $file->id]);
            }
            $erste ??= $n;
        }
        Yii::$app->queue->push(new BuergerJob(['id' => $erste->id]));
        return "Meldung $contentId: übernommen (" . mb_strlen($text) . ' Zeichen, ' . count($files) . ' Foto(s))';
    }

    /** Abwesenheitsnotizen, Zustellfehler und andere automatische Mails erkennen */
    private static function automatisch(array $kopf): bool
    {
        $auto = strtolower((string)($kopf['auto-submitted'][0] ?? 'no'));
        $prec = strtolower((string)($kopf['precedence'][0] ?? ''));
        $betreff = mb_strtolower((string)($kopf['subject'][0] ?? ''));
        return ($auto !== '' && $auto !== 'no')
            || in_array($prec, ['bulk', 'junk', 'list', 'auto_reply'], true)
            || isset($kopf['x-autoreply']) || isset($kopf['x-autorespond'])
            || preg_match('/^(abwesenheit|automatische antwort|out of office|autoreply|auto-reply|undeliverable|unzustellbar|mail delivery)/u', $betreff);
    }

    /** Zitierte frühere Mail und Signatur abschneiden */
    public static function ohneZitat(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $zeilen = [];
        foreach (explode("\n", $text) as $zeile) {
            $t = trim($zeile);
            if ($t === '--' || $t === '-- '
                || preg_match('/^(am|on) .{5,200}(schrieb|wrote)[^:]*:\s*$/iu', $t)
                || preg_match('/^-{2,}\s*(original|ursprüngliche|weitergeleitete|forwarded)/iu', $t)
                || preg_match('/^(von|from):\s.+/iu', $t) && $zeilen !== []
                || preg_match('/^(gesendet von|sent from) /iu', $t)) {
                break;
            }
            if (str_starts_with($t, '>')) {
                continue;
            }
            $zeilen[] = rtrim($zeile);
        }
        return trim(preg_replace("/\n{3,}/", "\n\n", implode("\n", $zeilen)));
    }
}

/** Sehr kleiner IMAP-Client (TLS, nur was der Abruf braucht); PHP im Container hat keine imap-Erweiterung */
class Imap
{
    private $s;
    private int $tag = 0;

    public function __construct(string $host, int $port)
    {
        $this->s = @stream_socket_client("ssl://$host:$port", $errno, $err, 20);
        if (!$this->s) {
            throw new \RuntimeException("IMAP-Verbindung fehlgeschlagen: $err");
        }
        stream_set_timeout($this->s, 60);
        fgets($this->s);
    }

    public function login(string $user, string $pass): void
    {
        $q = fn($v) => '"' . addcslashes($v, '"\\') . '"';
        $this->befehl('LOGIN ' . $q($user) . ' ' . $q($pass), 'Anmeldung');
    }

    /** Befehl senden, Antwort (inkl. Literale) zurückgeben; wirft bei NO/BAD */
    public function befehl(string $cmd, ?string $alsText = null): string
    {
        $tag = 'A' . (++$this->tag);
        fwrite($this->s, "$tag $cmd\r\n");
        $antwort = '';
        while (($zeile = fgets($this->s)) !== false) {
            if (preg_match('/\{(\d+)\}\r\n$/', $zeile, $m)) {
                $antwort .= $zeile;
                $rest = (int)$m[1];
                while ($rest > 0 && !feof($this->s)) {
                    $teil = fread($this->s, min(8192, $rest));
                    if ($teil === false || $teil === '') {
                        break;
                    }
                    $antwort .= $teil;
                    $rest -= strlen($teil);
                }
                continue;
            }
            if (str_starts_with($zeile, "$tag ")) {
                if (!preg_match("/^$tag OK/", $zeile)) {
                    throw new \RuntimeException(($alsText ?? 'IMAP-Befehl') . ' fehlgeschlagen: ' . trim(substr($zeile, strlen($tag) + 1)));
                }
                return $antwort;
            }
            $antwort .= $zeile;
        }
        throw new \RuntimeException('IMAP-Verbindung abgebrochen');
    }

    /** @return int[] UIDs */
    public function suche(string $kriterien): array
    {
        $a = $this->befehl("UID SEARCH $kriterien");
        return preg_match('/^\* SEARCH ?([\d ]*)/m', $a, $m) ? array_map('intval', array_filter(explode(' ', trim($m[1])))) : [];
    }

    /** Name des Ordners mit dem Sonderattribut (z. B. \\Trash = Papierkorb, je nach Sprache anders benannt) */
    public function ordnerMit(string $attribut): ?string
    {
        foreach (explode("\n", $this->befehl('LIST "" "*"')) as $zeile) {
            if (stripos($zeile, $attribut) !== false && preg_match('/"([^"]+)"\s*$/', trim($zeile), $m)) {
                return $m[1];
            }
        }
        return null;
    }

    /** Empfänger-Kopfzeilen einer Mail (ohne sie als gelesen zu markieren) */
    public function kopfzeilen(int $uid): string
    {
        return $this->befehl("UID FETCH $uid BODY.PEEK[HEADER.FIELDS (TO CC DELIVERED-TO X-ORIGINAL-TO)]");
    }

    /** Ganze Mail holen, ohne sie als gelesen zu markieren */
    public function hole(int $uid): string
    {
        $a = $this->befehl("UID FETCH $uid BODY.PEEK[]");
        if (!preg_match('/\{(\d+)\}\r\n/', $a, $m, PREG_OFFSET_CAPTURE)) {
            throw new \RuntimeException("Mail $uid nicht lesbar");
        }
        return substr($a, $m[0][1] + strlen($m[0][0]), (int)$m[1][0]);
    }

    public function logout(): void
    {
        try {
            $this->befehl('LOGOUT');
        } catch (\Throwable $e) {
        }
        fclose($this->s);
    }
}

/** Kleiner MIME-Leser: Kopfzeilen, Text (text/plain, sonst aus HTML) und Anhänge */
class Mime
{
    /** @return array{kopf: array<string, string[]>, text: string, anhaenge: array<int, array{typ: string, name: string, daten: string}>} */
    public static function parse(string $roh): array
    {
        $erg = ['kopf' => [], 'text' => '', 'html' => '', 'anhaenge' => []];
        self::teil($roh, $erg, true);
        if (trim($erg['text']) === '' && $erg['html'] !== '') {
            $html = preg_replace('#<(br|/p|/div|/li|/tr)[^>]*>#i', "\n", $erg['html']);
            $html = preg_replace('#<blockquote.*?</blockquote>#is', '', $html);
            $erg['text'] = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
        unset($erg['html']);
        return $erg;
    }

    private static function teil(string $roh, array &$erg, bool $oben): void
    {
        $roh = str_replace("\r\n", "\n", $roh);
        [$kopfText, $body] = array_pad(explode("\n\n", $roh, 2), 2, '');
        $kopf = self::kopf($kopfText);
        if ($oben) {
            $erg['kopf'] = $kopf;
        }
        $ct = $kopf['content-type'][0] ?? 'text/plain';
        $typ = strtolower(trim(explode(';', $ct)[0]));
        if (str_starts_with($typ, 'multipart/')) {
            if (!preg_match('/boundary="?([^";]+)"?/i', $ct, $m)) {
                return;
            }
            $teile = explode('--' . $m[1], $body);
            array_shift($teile);
            foreach ($teile as $t) {
                if (str_starts_with($t, '--')) {
                    break;
                }
                self::teil(ltrim($t, "\n"), $erg, false);
            }
            return;
        }
        if ($typ === 'message/rfc822') {
            return; // weitergeleitete Mails nicht auswerten
        }
        $daten = self::dekodieren($body, strtolower(trim($kopf['content-transfer-encoding'][0] ?? '')));
        $disp = strtolower($kopf['content-disposition'][0] ?? '');
        $name = '';
        if (preg_match('/(?:file)?name\*?="?([^";]+)"?/i', ($kopf['content-disposition'][0] ?? '') . ';' . $ct, $m)) {
            $name = self::wort(trim($m[1]));
        }
        if (str_starts_with($typ, 'image/') || str_starts_with($disp, 'attachment')) {
            $erg['anhaenge'][] = ['typ' => $typ, 'name' => basename($name), 'daten' => $daten];
            return;
        }
        if ($typ === 'text/plain' || $typ === 'text/html') {
            $charset = preg_match('/charset="?([^";]+)"?/i', $ct, $m) ? $m[1] : 'UTF-8';
            $text = @mb_convert_encoding($daten, 'UTF-8', $charset) ?: $daten;
            $erg[$typ === 'text/plain' ? 'text' : 'html'] .= $text;
        }
    }

    /** @return array<string, string[]> Kopfzeilen (Name klein), Werte dekodiert */
    private static function kopf(string $text): array
    {
        $text = preg_replace("/\n[ \t]+/", ' ', $text);
        $kopf = [];
        foreach (explode("\n", $text) as $zeile) {
            if (str_contains($zeile, ':')) {
                [$name, $wert] = explode(':', $zeile, 2);
                $kopf[strtolower(trim($name))][] = self::wort(trim($wert));
            }
        }
        return $kopf;
    }

    private static function wort(string $wert): string
    {
        $d = @iconv_mime_decode($wert, ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8');
        return $d !== false ? $d : $wert;
    }

    private static function dekodieren(string $body, string $enc): string
    {
        return match ($enc) {
            'base64' => (string)base64_decode(preg_replace('/\s+/', '', $body)),
            'quoted-printable' => quoted_printable_decode($body),
            default => $body,
        };
    }
}
