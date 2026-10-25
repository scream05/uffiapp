<?php

namespace humhub\modules\vereine\components;

use humhub\modules\vereine\models\Verein;
use humhub\modules\vereine\models\VereinMannschaft;
use humhub\modules\vereine\models\VereinSpiel;
use Yii;

/**
 * Spiele, Ergebnisse und Tabellen von Fußballvereinen beim Bayerischen Fußball-Verband (bfv.de).
 *
 * Die Mannschaften stehen auf der Vereinsseite bfv.de/vereine/-/<bfv_id>; Spiele und Tabellen liefert die
 * Widget-Schnittstelle des BFV als JSON (dieselbe, die Vereine auf ihren Websites einbinden). Auf den Webseiten
 * sind die Ergebnisse per Schrift verschleiert, in der Schnittstelle nicht. Den Spielort gibt es nur auf der
 * Vereinsseite (Spiele der nächsten Tage) bzw. auf der Seite des einzelnen Spiels.
 */
class Bfv
{
    public const API = 'https://widget-prod.bfv.de/api/service/widget/v1/';
    public const WEB = 'https://www.bfv.de/';

    /** Spielort für kommende Spiele so viele Tage im Voraus nachschlagen, höchstens so viele Seiten je Lauf */
    public const ORT_TAGE = 14;
    public const ORT_SEITEN = 20;

    /** Mannschaftsarten ohne Tabelle (Kinderfußball nach den DFB-Spielformen) */
    public const OHNE_TABELLE = ['E-Junioren', 'E-Juniorinnen', 'F-Junioren', 'F-Juniorinnen', 'G-Junioren', 'G-Juniorinnen'];

    /** Testhilfe: ersetzt den Abruf (URL => Antwort) */
    public static $holen = null;

    public static function holen(string $url): string
    {
        if (self::$holen !== null) {
            return (self::$holen)($url);
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_USERAGENT => 'community.rundumuffni.de Vereinsspiele',
        ]);
        $raw = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($raw === false || $status !== 200) {
            throw new \RuntimeException("HTTP $status bei $url");
        }
        return $raw;
    }

    private static function json(string $url): array
    {
        $d = json_decode(self::holen($url), true);
        if (!is_array($d) || ($d['state'] ?? null) !== 0 || !is_array($d['data'] ?? null)) {
            throw new \RuntimeException("Unerwartete Antwort von $url");
        }
        return $d['data'];
    }

    /**
     * Gleicht Mannschaften, Spiele und Tabellen eines Vereins ab. Schlägt ein Abruf fehl, bleiben die bisherigen
     * Daten der betroffenen Mannschaft stehen.
     *
     * @return string[] Meldungen (Fehler beginnen mit „!“)
     */
    public static function abrufen(Verein $verein): array
    {
        $log = [];
        $jetzt = date('Y-m-d H:i:s');
        $html = self::holen(self::WEB . 'vereine/-/' . rawurlencode($verein->bfv_id));
        preg_match_all('#bfv\.de/mannschaften/[^/"\s]+/([0-9A-Z]{32})#', $html, $m);
        $teamIds = array_values(array_unique($m[1]));
        if (!$teamIds) {
            throw new \RuntimeException('Keine Mannschaften auf der Vereinsseite gefunden');
        }
        $orte = self::orteAusSeite($html);

        $gesehen = [];
        foreach ($teamIds as $teamId) {
            try {
                $data = self::json(self::API . 'team/' . $teamId . '/matches');
                $team = $data['team'];
                if (($team['clubId'] ?? '') !== $verein->bfv_id && !str_starts_with((string)$team['name'], '(SG)')) {
                    $log[] = "! {$team['name']}: gehört nicht zum Verein, übersprungen";
                    continue;
                }
                $mannschaft = VereinMannschaft::findOne($teamId) ?? new VereinMannschaft(['id' => $teamId]);
                $mannschaft->setAttributes([
                    'verein_id' => $verein->id,
                    'name' => mb_substr(trim($team['name']), 0, 150),
                    'typ' => mb_substr(trim($team['typeName'] ?? '') ?: 'Mannschaft', 0, 50),
                    'liga' => $team['competitionName'] ?? null,
                    'liga_id' => $team['compoundId'] ?? null,
                    'aktualisiert_am' => $jetzt,
                ], false);
                if (!empty($team['clubName']) && ($team['clubId'] ?? '') === $verein->bfv_id && $verein->bfv_name !== $team['clubName']) {
                    $verein->updateAttributes(['bfv_name' => mb_substr($team['clubName'], 0, 150)]);
                }
                // Im Kinderfußball (bis U11) gibt es bewusst keine Tabellen
                if ($mannschaft->liga_id && !in_array($mannschaft->typ, self::OHNE_TABELLE, true)) {
                    try {
                        $mannschaft->tabelle = json_encode(self::tabelle($mannschaft->liga_id, $teamId), JSON_UNESCAPED_UNICODE);
                    } catch (\Throwable $e) {
                        $log[] = "! Tabelle {$mannschaft->name}: " . $e->getMessage();
                    }
                } else {
                    $mannschaft->tabelle = null;
                }
                $mannschaft->save(false);
                $gesehen[] = $teamId;

                $spielIds = [];
                foreach ($data['matches'] as $s) {
                    if (!preg_match('/^(\d{2})\.(\d{2})\.(\d{4})$/', (string)$s['kickoffDate'], $d)
                        || in_array('SPIELFREI', [mb_strtoupper(trim((string)$s['homeTeamName'])), mb_strtoupper(trim((string)$s['guestTeamName']))], true)) {
                        continue;
                    }
                    $spielIds[] = $s['matchId'];
                    $spiel = VereinSpiel::findOne(['mannschaft_id' => $teamId, 'spiel_id' => $s['matchId']])
                        ?? new VereinSpiel(['mannschaft_id' => $teamId, 'spiel_id' => $s['matchId']]);
                    $spiel->setAttributes([
                        'datum' => "$d[3]-$d[2]-$d[1]",
                        'uhrzeit' => preg_match('/^\d{2}:\d{2}$/', (string)$s['kickoffTime']) ? $s['kickoffTime'] : null,
                        'wettbewerb' => $s['competitionName'] ?? null,
                        'wettbewerb_typ' => $s['competitionType'] ?? null,
                        'heim' => mb_substr(trim((string)$s['homeTeamName']), 0, 150) ?: '?',
                        'gast' => mb_substr(trim((string)$s['guestTeamName']), 0, 150) ?: '?',
                        'heim_eigen' => (int)(($s['homeTeamPermanentId'] ?? '') === $teamId),
                        'ergebnis' => trim((string)($s['result'] ?? '')) ?: null,
                        'aktualisiert_am' => $jetzt,
                    ], false);
                    if (isset($orte[$s['matchId']])) {
                        $spiel->ort = $orte[$s['matchId']];
                    }
                    $spiel->save(false);
                }
                // Spiele, die der BFV nicht mehr führt (verlegt mit neuer Kennung, gestrichen)
                $weg = VereinSpiel::deleteAll(['and', ['mannschaft_id' => $teamId], ['not in', 'spiel_id', $spielIds ?: ['-']]]);
                $log[] = sprintf('%s (%s): %d Spiele%s', $mannschaft->name, $mannschaft->typ, count($spielIds), $weg ? ", $weg entfernt" : '');
            } catch (\Throwable $e) {
                $log[] = "! Mannschaft $teamId: " . $e->getMessage();
            }
        }
        // Mannschaften, die nicht mehr auf der Vereinsseite stehen (samt Spielen)
        foreach (VereinMannschaft::find()->where(['verein_id' => $verein->id])->andWhere(['not in', 'id', $teamIds])->all() as $alt) {
            $log[] = "- {$alt->name} ({$alt->typ}) nicht mehr beim Verein";
            $alt->delete();
        }
        $log[] = 'Spielorte nachgeschlagen: ' . self::orteNachschlagen($verein);
        $verein->refresh();
        $log[] = 'Spielberichte: ' . self::letzteSpieleTicker($verein);
        return $log;
    }

    /** Live-Abfrage: ab so vielen Minuten vor bis so viele Minuten nach dem Anstoß */
    public const LIVE_VORHER = 10;
    public const LIVE_NACHHER = 180;

    /** Ab dieser Minute nach Anstoß holt die Live-Abfrage alle 5 Minuten auch das Ergebnis aus der Schnittstelle */
    public const ERGEBNIS_AB = 100;

    /** Mannschaftsarten mit Spielernamen und Kommentaren im Liveticker (sonst nur Ereignis, Minute, Stand: Minderjährige) */
    public const MIT_NAMEN = ['Herren', 'Frauen', 'Senioren', 'Alte Herren'];

    /**
     * Liveticker eines Spiels von bfv.de (Seite …/liveticker, Daten im eingebetteten JSON). Namen und Kommentare
     * stehen dort im Klartext; die verschleierten Angaben der übrigen Spielseiten werden nicht verwendet.
     *
     * @return array|null ['status' => live|beendet, 'stand' => '1:0'|null, 'minute' => int|null, 'ereignisse' => [...]],
     *                    null wenn es (noch) keinen Ticker gibt
     */
    public static function ticker(string $spielId, bool $mitNamen): ?array
    {
        $html = self::holen(self::WEB . 'ergebnisse/spiel/-/' . rawurlencode($spielId) . '/liveticker');
        $daten = '';
        preg_match_all('/self\.__next_f\.push\(\[1,"(.*?)"\]\)<\/script>/s', $html, $m);
        foreach ($m[1] as $teil) {
            $daten .= json_decode('"' . $teil . '"') ?? '';
        }
        $eintraege = null;
        $pos = 0;
        while (($pos = strpos($daten, '"events":[', $pos)) !== false) {
            $pos += 9;
            $liste = self::jsonArray($daten, $pos);
            if ($liste && isset($liste[0]['typeId'])) {
                $eintraege = $liste;
                break;
            }
        }
        if (!$eintraege) {
            return null;
        }
        usort($eintraege, fn($a, $b) => strcmp((string)($b['timestamp'] ?? ''), (string)($a['timestamp'] ?? '')));
        $ereignisse = [];
        $status = 'live';
        $stand = null;
        $minute = null;
        foreach ($eintraege as $e) {
            $text = trim((string)($e['description'] ?? ''));
            $min = is_numeric($e['minute'] ?? null) ? (int)$e['minute'] : null;
            $score = preg_match('/^\d+:\d+$/', (string)($e['score'] ?? '')) ? $e['score'] : null;
            $stand ??= $score;
            $minute ??= $min;
            if (preg_match('/Abpfiff|Spielende/u', $text)) {
                $status = 'beendet';
            }
            $typ = (int)($e['typeId'] ?? 0);
            $art = match (true) {
                (bool)preg_match('/Gelb-?[Rr]ot/u', $text) => 'gelbrot',
                (bool)preg_match('/Rote Karte/u', $text) => 'rot',
                (bool)preg_match('/Gelbe Karte/u', $text) => 'gelb',
                $typ === 1 || (bool)preg_match('/^(Tor|Eigentor)\b/u', $text) => 'tor',
                (bool)preg_match('/Elfmeter/u', $text) => 'info',
                in_array($typ, [28, 29], true) || (bool)preg_match('/Anpfiff|Halbzeit|Abpfiff/u', $text) => 'phase',
                $typ === 99 && $mitNamen => 'info',
                default => null,
            };
            if ($art === null) {
                continue; // Ecken, Freistöße, Wechsel, Abseits, Torschüsse
            }
            $kommentar = trim((string)($e['comment'] ?? ''));
            if ($mitNamen && ($gesperrt = self::gesperrteNamen())) {
                // Widerspruch (Datenschutzerklärung): diese Namen nie zeigen
                $text = str_ireplace($gesperrt, '–', $text);
                $kommentar = str_ireplace($gesperrt, '–', $kommentar);
            }
            if (!$mitNamen) {
                // „Tor durch Vorname Name (9)“ → „Tor“; Mannschaftsnamen („Tor für TSV …“) bleiben
                $text = trim(preg_replace('/\s+(durch|für)\s+[^()]*?\s*\(\d+\)/u', '', $text));
                $kommentar = '';
            }
            if ($text === '' && $kommentar === '') {
                continue;
            }
            $ereignisse[] = ['m' => $min, 's' => $score, 't' => mb_substr($text, 0, 200), 'k' => mb_substr($kommentar, 0, 600), 'art' => $art];
        }
        return ['status' => $status, 'stand' => $stand, 'minute' => $minute, 'ereignisse' => $ereignisse];
    }

    /** Namen, die nach einem Widerspruch nicht in Spielberichten erscheinen (Moduleinstellung, /app/yii vereine/name-sperren) */
    public static function gesperrteNamen(): array
    {
        return json_decode((string)Yii::$app->getModule('vereine')->settings->get('tickerGesperrt', '[]'), true) ?: [];
    }

    /** JSON-Array ab Position $pos (öffnende Klammer) lesen */
    private static function jsonArray(string $s, int $pos): ?array
    {
        $tiefe = 0;
        $inText = false;
        $n = strlen($s);
        for ($i = $pos; $i < $n; $i++) {
            $c = $s[$i];
            if ($inText) {
                if ($c === '\\') {
                    $i++;
                } elseif ($c === '"') {
                    $inText = false;
                }
            } elseif ($c === '"') {
                $inText = true;
            } elseif ($c === '[' || $c === '{') {
                $tiefe++;
            } elseif ($c === ']' || $c === '}') {
                if (--$tiefe === 0) {
                    $d = json_decode(substr($s, $pos, $i - $pos + 1), true);
                    return is_array($d) ? $d : null;
                }
            }
        }
        return null;
    }

    /** Ticker eines Spiels abrufen und speichern; liefert den Status oder null */
    public static function tickerSpeichern(VereinSpiel $spiel): ?string
    {
        $mitNamen = in_array($spiel->mannschaft->typ ?? '', self::MIT_NAMEN, true);
        $t = self::ticker($spiel->spiel_id, $mitNamen);
        $werte = $t === null
            ? ['ticker' => null, 'ticker_status' => 'keiner', 'ticker_stand' => null, 'ticker_minute' => null]
            : ['ticker' => json_encode($t['ereignisse'], JSON_UNESCAPED_UNICODE), 'ticker_status' => $t['status'],
                'ticker_stand' => $t['stand'], 'ticker_minute' => $t['minute']];
        // Dasselbe Spiel kann bei zwei eigenen Mannschaften stehen
        VereinSpiel::updateAll($werte + ['ticker_am' => date('Y-m-d H:i:s')], ['spiel_id' => $spiel->spiel_id]);
        $spiel->setAttributes($werte, false);
        return $werte['ticker_status'];
    }

    /**
     * Minütlich (Crontab): Liveticker der Spiele, die gerade laufen; gegen Ende alle 5 Minuten auch das Ergebnis
     * aus der Schnittstelle. Ohne laufendes Spiel wird nichts abgerufen.
     * @return string[] Meldungen
     */
    public static function live(?int $jetzt = null): array
    {
        $jetzt ??= time();
        $log = [];
        $spiele = VereinSpiel::find()->with('mannschaft')->where(['datum' => [date('Y-m-d', $jetzt - 86400), date('Y-m-d', $jetzt)]])
            ->andWhere(['not', ['uhrzeit' => null]])->all();
        $ergebnisTeams = [];
        foreach ($spiele as $spiel) {
            $seit = intdiv($jetzt - $spiel->getZeitpunkt(), 60);
            if ($seit < -self::LIVE_VORHER || $spiel->getZustand() !== null || $spiel->ticker_status === 'beendet') {
                continue;
            }
            if ($seit > self::LIVE_NACHHER) {
                if ($spiel->ticker_status === 'live') {
                    VereinSpiel::updateAll(['ticker_status' => 'beendet'], ['spiel_id' => $spiel->spiel_id]); // Ticker nicht zu Ende geführt
                    $log[] = "{$spiel->heim} – {$spiel->gast}: Ticker ohne Abpfiff, als beendet markiert";
                }
                continue;
            }
            try {
                $status = self::tickerSpeichern($spiel);
                $log[] = "{$spiel->heim} – {$spiel->gast}: Ticker $status" . ($spiel->ticker_stand ? " {$spiel->ticker_stand}" : '')
                    . ($spiel->ticker_minute !== null ? " ({$spiel->ticker_minute}.)" : '');
            } catch (\Throwable $e) {
                $log[] = "! {$spiel->spiel_id}: " . $e->getMessage();
            }
            if ($seit >= self::ERGEBNIS_AB && $spiel->getTore() === null && intdiv($jetzt, 60) % 5 === 0) {
                $ergebnisTeams[$spiel->mannschaft_id] = true;
            }
        }
        foreach (array_keys($ergebnisTeams) as $teamId) {
            try {
                $n = self::ergebnisseAktualisieren($teamId);
                $log[] = "Ergebnisse $teamId: $n neu";
            } catch (\Throwable $e) {
                $log[] = "! Ergebnisse $teamId: " . $e->getMessage();
            }
        }
        return $log;
    }

    /** Nur die Ergebnisse vorhandener Spiele einer Mannschaft aus der Schnittstelle übernehmen; liefert die Anzahl Änderungen */
    public static function ergebnisseAktualisieren(string $teamId): int
    {
        $n = 0;
        foreach (self::json(self::API . 'team/' . $teamId . '/matches')['matches'] as $s) {
            $ergebnis = trim((string)($s['result'] ?? '')) ?: null;
            $n += VereinSpiel::updateAll(['ergebnis' => $ergebnis, 'aktualisiert_am' => date('Y-m-d H:i:s')],
                ['and', ['mannschaft_id' => $teamId, 'spiel_id' => $s['matchId']], ['or', ['ergebnis' => null], ['!=', 'ergebnis', (string)$ergebnis]]]);
        }
        return $n;
    }

    /**
     * Täglich nach dem Abgleich: Spielbericht (Ticker) für das letzte Spiel je Mannschaft holen, falls noch nicht
     * vollständig; Ticker älterer Spiele löschen (wir zeigen nur das letzte Spiel ausführlich).
     */
    private static function letzteSpieleTicker(Verein $verein): string
    {
        $behalten = [];
        $geholt = 0;
        foreach ($verein->mannschaften as $m) {
            $letztes = null;
            foreach (VereinSpiel::find()->where(['mannschaft_id' => $m->id])->andWhere(['<=', 'datum', date('Y-m-d')])
                         ->andWhere(['>=', 'datum', date('Y-m-d', strtotime('-60 days'))])
                         ->orderBy(['datum' => SORT_DESC, 'uhrzeit' => SORT_DESC])->all() as $s) {
                if ($s->istVorbei() && $s->getZustand() === null) {
                    $letztes = $s;
                    break;
                }
            }
            if ($letztes === null) {
                continue;
            }
            $behalten[] = $letztes->spiel_id;
            // Fertige Ticker nicht erneut holen; ohne Ticker drei Tage lang täglich nachsehen
            if ($letztes->ticker_status === 'beendet' && $letztes->ticker !== null
                || $letztes->ticker_status === 'keiner' && $letztes->datum < date('Y-m-d', strtotime('-3 days'))) {
                continue;
            }
            try {
                self::tickerSpeichern($letztes);
                if ($letztes->ticker_status === 'live') {
                    VereinSpiel::updateAll(['ticker_status' => 'beendet'], ['spiel_id' => $letztes->spiel_id]);
                }
                $geholt++;
                if (self::$holen === null) {
                    usleep(500000);
                }
            } catch (\Throwable $e) {
                Yii::warning("Ticker {$letztes->spiel_id}: " . $e->getMessage(), 'vereine');
            }
        }
        $ids = array_map(fn($m) => $m->id, $verein->mannschaften) ?: ['-'];
        $geleert = VereinSpiel::updateAll(['ticker' => null, 'ticker_stand' => null, 'ticker_minute' => null],
            ['and', ['mannschaft_id' => $ids], ['not', ['ticker' => null]], ['not in', 'spiel_id', $behalten ?: ['-']], ['!=', 'ticker_status', 'live']]);
        return "$geholt geholt, $geleert ältere geleert";
    }

    /** Spielorte aus der Vereinsseite: Spiel-ID => „Sportanlage …, Platz 1 | Straße | PLZ Ort“ */
    public static function orteAusSeite(string $html): array
    {
        $orte = [];
        foreach (array_slice(explode('class="bfv-spieltag-eintrag"', $html), 1) as $block) {
            if (preg_match('#ergebnisse/spiel/-/([0-9A-Z]{32})#', $block, $id)
                && preg_match('#bfv-spieltag-eintrag__location">\s*(.*?)\s*</div>#s', $block, $ort)) {
                $orte[$id[1]] = self::ortText($ort[1]);
            }
        }
        return $orte;
    }

    /** Ort als Text; „Keine Angabe“ wird zu '' (nicht erneut nachschlagen, nichts anzeigen) */
    private static function ortText(string $html): string
    {
        $ort = mb_substr(trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'))), 0, 255);
        return strcasecmp($ort, 'Keine Angabe') === 0 ? '' : $ort;
    }

    /** Fehlende Spielorte kommender Spiele von der Seite des Spiels holen; liefert die Anzahl */
    private static function orteNachschlagen(Verein $verein): int
    {
        $n = 0;
        $spiele = VereinSpiel::find()->innerJoinWith('mannschaft m', false)->where(['m.verein_id' => $verein->id, 'ort' => null])
            ->andWhere(['between', 'datum', date('Y-m-d'), date('Y-m-d', strtotime('+' . self::ORT_TAGE . ' days'))])
            ->orderBy(['datum' => SORT_ASC])->limit(self::ORT_SEITEN)->all();
        foreach ($spiele as $spiel) {
            try {
                $html = self::holen(self::WEB . 'ergebnisse/spiel/-/' . rawurlencode($spiel->spiel_id));
                if (preg_match('#>Adresse</span><span[^>]*>(.*?)</span>#s', $html, $m)) {
                    $ort = self::ortText($m[1]);
                    // Dasselbe Spiel kann bei zwei eigenen Mannschaften stehen
                    VereinSpiel::updateAll(['ort' => $ort], ['spiel_id' => $spiel->spiel_id]);
                    $n++;
                }
                if (self::$holen === null) {
                    usleep(500000);
                }
            } catch (\Throwable $e) {
                Yii::warning("Spielort {$spiel->spiel_id}: " . $e->getMessage(), 'vereine');
            }
        }
        return $n;
    }

    /** Tabelle einer Liga, kompakt */
    public static function tabelle(string $ligaId, string $teamId): array
    {
        $zeilen = [];
        foreach (self::json(self::API . 'competition/' . rawurlencode($ligaId) . '/table')['table'] ?? [] as $z) {
            $zeilen[] = [
                'platz' => (int)$z['position'],
                'name' => (string)($z['team']['name'] ?? ''),
                'spiele' => (int)$z['matches'],
                's' => (int)$z['matchesWon'],
                'u' => (int)$z['matchesDrawn'],
                'n' => (int)$z['matchesLost'],
                'diff' => (int)$z['goalsDiff'],
                'punkte' => (int)$z['points'],
                'eigen' => ($z['team']['permanentId'] ?? '') === $teamId,
            ];
        }
        return $zeilen;
    }

    /** Ort ohne doppelte Angaben: „Sportanlage Uffenheim An den Seewiesen, Platz 1“ */
    public static function ortKurz(?string $ort): string
    {
        return trim(explode(' | ', (string)$ort)[0]);
    }

    /** Datum/Uhrzeit (Ortszeit) in der Sprache der Oberfläche, Skelett wie „EEEEdMMMM“ oder „Hm“ */
    public static function format(int $zeitpunkt, string $skelett): string
    {
        $locale = Yii::$app->language;
        $muster = (new \IntlDatePatternGenerator($locale))->getBestPattern($skelett);
        return (string)(new \IntlDateFormatter($locale, \IntlDateFormatter::NONE, \IntlDateFormatter::NONE, 'Europe/Berlin', null, $muster))
            ->format($zeitpunkt);
    }

    /**
     * Kasten auf der Vereinsseite und im Verzeichnis: die nächsten und die letzten Spiele aller Mannschaften
     * @return array{kommend: VereinSpiel[], ergebnisse: VereinSpiel[], label: string[]}
     */
    public static function kurz(Verein $verein, int $anzahl = 4): array
    {
        $label = [];
        foreach (VereinMannschaft::sortieren($verein->mannschaften) as $m) {
            $label[$m->id] = $m->getLabel();
        }
        $q = fn() => VereinSpiel::find()->where(['mannschaft_id' => array_keys($label) ?: ['-']]);
        $kommend = array_values(array_filter($q()->andWhere(['>=', 'datum', date('Y-m-d')])
            ->orderBy(['datum' => SORT_ASC, 'uhrzeit' => SORT_ASC])->limit($anzahl + 10)->all(), fn($s) => !$s->istVorbei()));
        $ergebnisse = array_values(array_filter($q()->andWhere(['<=', 'datum', date('Y-m-d')])->andWhere(['>=', 'datum', date('Y-m-d', strtotime('-14 days'))])
            ->orderBy(['datum' => SORT_DESC, 'uhrzeit' => SORT_DESC])->limit($anzahl + 10)->all(), fn($s) => $s->istVorbei()));
        return ['kommend' => array_slice($kommend, 0, $anzahl), 'ergebnisse' => array_slice($ergebnisse, 0, $anzahl), 'label' => $label];
    }

    /** Zeitpunkt des letzten Abrufs eines Vereins */
    public static function stand(Verein $verein): ?string
    {
        return VereinMannschaft::find()->where(['verein_id' => $verein->id])->max('aktualisiert_am');
    }

    /** Für Uffi: Ergebnisse der letzten 8 Tage, Spiele der nächsten 14 Tage und Tabellenplätze */
    public static function forAssistant(): ?string
    {
        $vereine = Verein::find()->where(['not', ['bfv_id' => null]])->orderBy(['name' => SORT_ASC])->all();
        if (!$vereine) {
            return null;
        }
        $tage = ['So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa'];
        $out = [];
        foreach ($vereine as $verein) {
            $mannschaften = VereinMannschaft::sortieren($verein->mannschaften);
            if (!$mannschaften) {
                continue;
            }
            $label = [];
            foreach ($mannschaften as $m) {
                $label[$m->id] = $m->getLabel();
            }
            $zeile = function (VereinSpiel $s) use ($label, $tage) {
                $t = $s->getZeitpunkt();
                $text = '- ' . $tage[date('w', $t)] . ' ' . date('d.m.', $t) . ($s->uhrzeit ? ' ' . $s->uhrzeit . ' Uhr' : '')
                    . ' ' . $label[$s->mannschaft_id] . ': ' . $s->heim . ' – ' . $s->gast;
                if ($s->laeuft()) {
                    $text .= ' – läuft gerade' . ($s->getAnzeigeStand() !== null ? ', Stand ' . $s->getAnzeigeStand() : '')
                        . ($s->ticker_minute !== null ? ", {$s->ticker_minute}. Minute" : '') . ' laut Liveticker (Stand vor bis zu 10 Minuten)';
                } elseif ($s->getAnzeigeStand() !== null) {
                    $text .= ' ' . $s->getAnzeigeStand();
                } elseif ($s->getZustand() !== null) {
                    $text .= ' (' . $s->getZustand() . ')';
                }
                $text .= ' (' . ($s->heim_eigen ? 'Heimspiel' : 'auswärts') . ($s->ort ? ', ' . self::ortKurz($s->ort) : '') . ')';
                if ($s->wettbewerb_typ && $s->wettbewerb_typ !== 'Meisterschaften') {
                    $text .= ', ' . $s->wettbewerb_typ;
                }
                return $text;
            };
            $vorbei = $kommend = [];
            foreach (VereinSpiel::find()->where(['mannschaft_id' => array_keys($label)])
                         ->andWhere(['between', 'datum', date('Y-m-d', strtotime('-8 days')), date('Y-m-d', strtotime('+14 days'))])
                         ->orderBy(['datum' => SORT_ASC, 'uhrzeit' => SORT_ASC])->all() as $s) {
                if ($s->istVorbei()) {
                    $vorbei[] = $zeile($s);
                } else {
                    $kommend[] = $zeile($s);
                }
            }
            $tabellen = [];
            foreach ($mannschaften as $m) {
                if (($p = $m->getPlatz()) !== null) {
                    $tabellen[] = "- {$label[$m->id]} ({$m->liga}): Platz {$p['platz']} von " . count($m->getTabelleList())
                        . ", {$p['punkte']} Punkte aus {$p['spiele']} Spielen";
                }
            }
            $name = $verein->bfv_name ?: $verein->name;
            $stand = self::stand($verein);
            $out[] = "## Fußball: Spiele und Ergebnisse von $name (Quelle: BFV, Stand " . date('d.m. H:i', strtotime((string)$stand)) . " Uhr; "
                . "Spielplan, Ergebnisse und Tabellen aller Mannschaften: " . $verein->getSpieleUrl(true) . ")\n"
                . "Mannschaften: " . implode(', ', $label) . "\n"
                . "Ergebnisse der letzten 8 Tage (ohne Ergebnis = noch nicht gemeldet):\n" . ($vorbei ? implode("\n", $vorbei) : '(keine)') . "\n"
                . "Kommende Spiele (nächste 14 Tage):\n" . ($kommend ? implode("\n", $kommend) : '(keine)') . "\n"
                . "Tabellenplätze:\n" . ($tabellen ? implode("\n", $tabellen) : '(keine)');
        }
        return $out ? implode("\n\n", $out) : null;
    }
}
