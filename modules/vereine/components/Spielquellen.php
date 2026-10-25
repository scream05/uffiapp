<?php

namespace humhub\modules\vereine\components;

use humhub\modules\vereine\models\Verein;
use humhub\modules\vereine\models\VereinMannschaft;
use humhub\modules\vereine\models\VereinSpiel;
use Yii;

/**
 * Spiele, Ergebnisse und Tabellen weiterer Sportarten neben dem Fußball (components/Bfv.php). Welche Quelle ein
 * Verein hat, steht in verein.spiele_quelle, die Kennung dort in verein.bfv_id:
 *
 *   dbb  Basketball, Deutscher Basketball-Bund (basketball-bund.net, JSON-Schnittstelle der Webseite); Kennung = Vereinsnummer (clubId)
 *   bvv  Volleyball, Bayerischer Volleyball-Verband (volleyball.bayern, Webseiten); Kennung = Suchbegriff im Mannschaftsnamen
 *   bbv  Badminton, Bayerischer Badminton-Verband (badminton-bbv.de, Datenabruf der Webseite); Kennung = Vereinsnummer (07-0350)
 *
 * Jede Quelle liefert dieselbe Form (Mannschaften mit Tabelle, Spiele je Mannschaft), speichern() gleicht sie mit der
 * Datenbank ab. Übernommen werden nur Mannschaften, Paarungen, Ergebnisse, Hallen und Tabellen – keine Namen von
 * Spielern, Schiedsrichtern oder Mannschaftsführern und keine Telefonnummern.
 */
class Spielquellen
{
    /** Anzeige je Quelle; „diff“ ist die Spalte der Tabelle zwischen Bilanz und Punkten */
    public const QUELLEN = [
        'bfv' => ['sport' => 'Fußball', 'icon' => 'fa-futbol-o', 'kurz' => 'BFV', 'verband' => 'Bayerischer Fußball-Verband',
            'unentschieden' => true, 'diff' => 'Tordifferenz', 'diffKurz' => 'Diff.'],
        'dbb' => ['sport' => 'Basketball', 'icon' => 'fa-dribbble', 'kurz' => 'DBB', 'verband' => 'Deutscher Basketball Bund',
            'unentschieden' => false, 'diff' => 'Korbdifferenz', 'diffKurz' => 'Diff.'],
        'bvv' => ['sport' => 'Volleyball', 'icon' => 'fa-trophy', 'kurz' => 'BVV', 'verband' => 'Bayerischer Volleyball-Verband',
            'unentschieden' => false, 'diff' => 'Sätze', 'diffKurz' => 'Sätze'],
        'bbv' => ['sport' => 'Badminton', 'icon' => 'fa-trophy', 'kurz' => 'BBV', 'verband' => 'Bayerischer Badminton-Verband',
            'unentschieden' => true, 'diff' => 'Spiele', 'diffKurz' => 'Spiele'],
    ];

    public const DBB = 'https://www.basketball-bund.net/';
    public const BVV = 'https://volleyball.bayern';
    public const BBV = 'https://badminton-bbv.de';

    /** Volleyball: in diesen Bereichen nach Ligen mit eigenen Mannschaften suchen */
    public const BVV_BEREICHE = ['erwachsene/mittelfranken', 'erwachsene/unterfranken', 'erwachsene/ueberbezirkliche-ligen',
        'jugend/mittelfranken', 'jugend/unterfranken', 'jugend/ueberbezirkliche-meisterschaften'];

    /** Volleyball: alle Ligen der Bereiche neu durchsuchen, wenn die letzte Suche so viele Tage her ist */
    public const BVV_SUCHE_TAGE = 7;

    /** Halle für kommende Spiele so viele Tage im Voraus nachschlagen, höchstens so viele Seiten je Lauf */
    public const ORT_TAGE = 14;
    public const ORT_SEITEN = 20;

    /** Sortierung der Vereine: Fußball, Basketball, Volleyball, Badminton, darin nach Name */
    public static function reihenfolge(): array
    {
        return [new \yii\db\Expression("FIELD(spiele_quelle, 'bfv', 'dbb', 'bvv', 'bbv')") , 'name' => SORT_ASC];
    }

    public static function quelle(Verein $verein): array
    {
        return self::QUELLEN[$verein->spiele_quelle] ?? self::QUELLEN['bfv'];
    }

    /** Sportart in der Sprache der Oberfläche */
    public static function sport(Verein $verein): string
    {
        return Yii::t('VereineModule.base', self::quelle($verein)['sport']);
    }

    /** Seite des Vereins bei der Quelle */
    public static function vereinUrl(Verein $verein): string
    {
        $k = rawurlencode((string)$verein->bfv_id);
        return match ($verein->spiele_quelle) {
            'dbb' => ($liga = self::hauptliga($verein)) ? self::DBB . 'static/#/liga/' . rawurlencode($liga) : self::DBB,
            'bvv' => self::BVV . '/ergebnisse',
            'bbv' => self::BBV . "/liga/club/BBV/$k",
            default => Bfv::WEB . "vereine/-/$k",
        };
    }

    /** Liga der ersten Mannschaft (für den Link zum DBB, der keine Vereinsseiten hat) */
    private static function hauptliga(Verein $verein): ?string
    {
        foreach (VereinMannschaft::sortieren($verein->mannschaften) as $m) {
            if ($m->liga_id) {
                return $m->liga_id;
            }
        }
        return null;
    }

    /**
     * Abgleich eines Vereins mit seiner Quelle
     * @return string[] Meldungen (Fehler beginnen mit „!“)
     */
    public static function abrufen(Verein $verein): array
    {
        return match ($verein->spiele_quelle) {
            'dbb' => self::speichern($verein, self::dbb($verein)),
            'bvv' => self::speichern($verein, self::bvv($verein)),
            'bbv' => self::speichern($verein, self::bbv($verein)),
            default => Bfv::abrufen($verein),
        };
    }

    private static function pause(): void
    {
        if (Bfv::$holen === null) {
            usleep(300000);
        }
    }

    private static function json(string $url): array
    {
        $d = json_decode(Bfv::holen($url), true);
        if (!is_array($d)) {
            throw new \RuntimeException("Unerwartete Antwort von $url");
        }
        return $d;
    }

    private static function text(string $html): string
    {
        return trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    }

    /** „U14 männlich“, „Herren“, „Damen“, „Frauen“ … aus Liga- bzw. Altersklassenangaben */
    public static function typAus(string $text, string $erwachseneM = 'Herren', string $erwachseneW = 'Damen'): string
    {
        $g = match (true) {
            (bool)preg_match('/\b(weiblich|w)\b|Frauen|Damen|Juniorinnen|Mädchen/ui', $text) => 'w',
            (bool)preg_match('/\b(männlich|m)\b|Herren|Männer|Junioren|Jungen/ui', $text) => 'm',
            default => 'x',
        };
        if (preg_match('/\bU\s?(\d{1,2})(?:\s?([mw])\b)?/u', $text, $u)) {
            $g = ($u[2] ?? '') ?: $g;
            return 'U' . $u[1] . ' ' . ['w' => 'weiblich', 'm' => 'männlich', 'x' => 'mix'][$g];
        }
        return ['w' => $erwachseneW, 'm' => $erwachseneM, 'x' => 'Erwachsene'][$g];
    }

    // ---------- Basketball (DBB) ----------

    /** Bekannte Ligen eines Vereins (Moduleinstellung), weil die Schnittstelle nur Spiele der nächsten Wochen nach Verein liefert */
    private static function dbbLigen(Verein $verein, ?array $neu = null): array
    {
        $settings = Yii::$app->getModule('vereine')->settings;
        if ($neu !== null) {
            $settings->set('dbbLigen' . $verein->id, json_encode(array_values(array_unique($neu))));
            return $neu;
        }
        return json_decode((string)$settings->get('dbbLigen' . $verein->id, '[]'), true) ?: [];
    }

    private static function dbb(Verein $verein): array
    {
        $club = (int)$verein->bfv_id;
        $erg = ['mannschaften' => [], 'spiele' => [], 'fehler' => [], 'log' => []];
        $ligen = self::dbbLigen($verein);
        $d = self::json(self::DBB . "rest/club/id/$club/actualmatches?justHome=false&rangeDays=400");
        foreach ($d['data']['matches'] ?? [] as $s) {
            $ligen[] = (string)$s['ligaData']['ligaId'];
        }
        if (!empty($d['data']['club']['vereinsname']) && !$verein->bfv_name) {
            $verein->updateAttributes(['bfv_name' => mb_substr($d['data']['club']['vereinsname'], 0, 150)]);
        }
        $ligen = array_values(array_unique($ligen));
        $behalten = [];
        foreach ($ligen as $ligaId) {
            try {
                $plan = self::json(self::DBB . 'rest/competition/spielplan/id/' . rawurlencode($ligaId));
                if (($plan['status'] ?? '') !== '0' || !is_array($plan['data'] ?? null)) {
                    $erg['log'][] = "Liga $ligaId nicht mehr vorhanden";
                    continue; // vergangene Saison
                }
                $behalten[] = $ligaId;
                $liga = $plan['data']['ligaData'];
                $ligaName = trim((string)$liga['liganame']);
                $pokal = (bool)preg_match('/pokal/i', $ligaName);
                $typ = self::typAus(($liga['akName'] === 'Senioren' ? '' : $liga['akName'] . ' ') . $liga['geschlecht']);
                $eigene = [];
                foreach ($plan['data']['matches'] as $s) {
                    foreach (['homeTeam' => true, 'guestTeam' => false] as $seite => $heim) {
                        if (($s[$seite]['clubId'] ?? null) !== $club) {
                            continue;
                        }
                        $id = 'dbb:' . $s[$seite]['teamPermanentId'];
                        $eigene[$id] = $s[$seite]['seasonTeamId'];
                        if (!isset($erg['mannschaften'][$id]) || !$pokal && !empty($erg['mannschaften'][$id]['pokal'])) {
                            $erg['mannschaften'][$id] = ['name' => trim($s[$seite]['teamname']), 'typ' => $typ, 'pokal' => $pokal,
                                'liga' => $pokal ? null : $ligaName, 'liga_id' => $pokal ? null : (string)$ligaId, 'tabelle' => null];
                        }
                        $ergebnis = trim((string)$s['result']) ?: null;
                        if ($s['abgesagt']) {
                            $ergebnis = 'Abse.';
                        } elseif ($s['verzicht']) {
                            $ergebnis = 'Nichtantr.';
                        }
                        $erg['spiele'][$id][] = [
                            'spiel_id' => (string)$s['matchId'],
                            'datum' => $s['kickoffDate'],
                            'uhrzeit' => preg_match('/^\d{2}:\d{2}$/', (string)$s['kickoffTime']) ? $s['kickoffTime'] : null,
                            'wettbewerb' => $ligaName,
                            'wettbewerb_typ' => $pokal ? 'Pokale' : 'Meisterschaften',
                            'heim' => trim((string)($s['homeTeam']['teamname'] ?? '?')),
                            'gast' => trim((string)($s['guestTeam']['teamname'] ?? '?')),
                            'heim_eigen' => $heim,
                            'ergebnis' => $ergebnis,
                            'url' => self::DBB . 'static/#/liga/' . rawurlencode($ligaId),
                        ];
                    }
                }
                if (!$pokal && !empty($liga['tableExists']) && $eigene) {
                    self::pause();
                    $t = self::json(self::DBB . 'rest/competition/table/id/' . rawurlencode($ligaId));
                    $zeilen = [];
                    foreach ($t['data']['tabelle']['entries'] ?? [] as $z) {
                        $zeilen[] = ['platz' => (int)$z['rang'], 'name' => (string)$z['team']['teamname'], 'spiele' => (int)$z['anzspiele'],
                            's' => (int)$z['s'], 'u' => 0, 'n' => (int)$z['n'], 'diff' => (int)$z['korbdiff'], 'punkte' => (int)$z['anzGewinnpunkte'],
                            'eigen' => in_array($z['team']['seasonTeamId'] ?? null, $eigene, true)];
                    }
                    foreach (array_keys($eigene) as $id) {
                        if ($erg['mannschaften'][$id]['liga_id'] === (string)$ligaId) {
                            $erg['mannschaften'][$id]['tabelle'] = $zeilen;
                        }
                    }
                }
            } catch (\Throwable $e) {
                $erg['log'][] = "! Liga $ligaId: " . $e->getMessage();
                $erg['ligaFehler'] = true;
                $behalten[] = $ligaId;
            }
            self::pause();
        }
        self::dbbLigen($verein, $behalten);
        $erg['ort'] = function (VereinSpiel $s): ?string {
            $f = self::json(self::DBB . 'rest/match/id/' . rawurlencode($s->spiel_id) . '/matchInfo')['data']['matchInfo']['spielfeld'] ?? null;
            return $f ? implode(' | ', array_filter([trim((string)$f['bezeichnung']), trim((string)$f['strasse']),
                trim($f['plz'] . ' ' . $f['ort'])])) : null;
        };
        return $erg;
    }

    // ---------- Volleyball (BVV) ----------

    /** Ligen mit eigenen Mannschaften: gespeicherte Liste, wöchentlich (oder wenn eine Liga fehlt) neu gesucht */
    private static function bvvLigen(Verein $verein, bool $neuSuchen, array &$log): array
    {
        $settings = Yii::$app->getModule('vereine')->settings;
        $gespeichert = json_decode((string)$settings->get('bvvLigen' . $verein->id, '{}'), true) ?: [];
        if (!$neuSuchen && !empty($gespeichert['ligen']) && ($gespeichert['am'] ?? '') >= date('Y-m-d', strtotime('-' . self::BVV_SUCHE_TAGE . ' days'))) {
            return $gespeichert['ligen'];
        }
        $ligen = [];
        $durchsucht = 0;
        foreach (self::BVV_BEREICHE as $bereich) {
            $html = Bfv::holen(self::BVV . "/ergebnisse/$bereich");
            preg_match_all('#href="(/ergebnisse/' . preg_quote($bereich, '#') . '/wettbewerb-\d+)"#', $html, $m);
            foreach (array_unique($m[1]) as $pfad) {
                self::pause();
                $durchsucht++;
                if (self::bvvIstEigen(Bfv::holen(self::BVV . $pfad), $verein->bfv_id)) {
                    $ligen[] = $pfad;
                }
            }
        }
        $log[] = "Ligen durchsucht: $durchsucht, mit eigenen Mannschaften: " . count($ligen);
        $settings->set('bvvLigen' . $verein->id, json_encode(['am' => date('Y-m-d'), 'ligen' => $ligen]));
        return $ligen;
    }

    /** Steht der Suchbegriff im Namen (bzw. auf der Seite)? */
    public static function bvvIstEigen(string $name, string $suche): bool
    {
        $norm = fn($t) => mb_strtolower(preg_replace('/\s+/u', ' ', $t));
        return $suche !== '' && str_contains($norm($name), $norm($suche));
    }

    /** Teamname vereinheitlicht (Leerzeichen um „/“), zum Zuordnen von Pokal- zu Ligamannschaften */
    private static function bvvNorm(string $name): string
    {
        return mb_strtolower(preg_replace(['#\s*/\s*#u', '/\s+/u'], ['/', ' '], trim($name)));
    }

    /**
     * Eine Liga-Seite auslesen
     * @return array{liga: string, spiele: array[], tabelle: array[], teams: array<string,string>} teams: Name => Team-Kennung
     */
    public static function bvvLiga(string $html): array
    {
        $liga = preg_match('#<h2 class="text-uppercase[^"]*">\s*(.*?)\s*</h2>#s', $html, $m) ? self::text($m[1]) : '';
        $spiele = [];
        $bloecke = preg_split('#<h4 class="fs-5[^"]*"[^>]*>#', $html);
        array_shift($bloecke);
        foreach ($bloecke as $b) {
            if (!preg_match('#href="(/ergebnisse/[a-z-]+/[a-z-]+/spiel-(\d+))"#', $b, $link)
                || !preg_match('#(\d{2})\.(\d{2})\.(\d{4})#', substr($b, 0, 600), $d) || isset($spiele[$link[2]])) {
                continue;
            }
            preg_match_all('#<tr>\s*<th scope="row">(.*?)</th>(.*?)</tr>#s', $b, $zeilen, PREG_SET_ORDER);
            if (count($zeilen) < 2) {
                continue;
            }
            $team = [];
            foreach (array_slice($zeilen, 0, 2) as $z) {
                preg_match_all('#<td[^>]*>(.*?)</td>#s', $z[2], $zellen);
                $werte = array_map([self::class, 'text'], $zellen[1]);
                $team[] = ['name' => self::text($z[1]), 'saetze' => $werte[0] ?? '', 'baelle' => array_slice($werte, 1)];
            }
            $gespielt = $team[0]['baelle'] && $team[1]['baelle'];
            $details = [];
            foreach ($team[0]['baelle'] as $i => $p) {
                $details[] = $p . ':' . ($team[1]['baelle'][$i] ?? '');
            }
            $spiele[$link[2]] = [
                'spiel_id' => $link[2],
                'datum' => "$d[3]-$d[2]-$d[1]",
                'uhrzeit' => preg_match('#,\s*(\d{2}:\d{2})\s*Uhr#', substr($b, 0, 600), $t) ? $t[1] : null,
                'heim' => $team[0]['name'],
                'gast' => $team[1]['name'],
                'ergebnis' => $gespielt ? $team[0]['saetze'] . ':' . $team[1]['saetze'] : null,
                'details' => $gespielt ? implode(', ', $details) : null,
                'url' => self::BVV . $link[1],
            ];
        }
        $tabelle = [];
        $teams = [];
        if (($p = strpos($html, 'id="tabellenrunde0"')) !== false) {
            $t = substr($html, $p, (strpos($html, '</table>', $p) ?: strlen($html)) - $p);
            preg_match_all('#<tr>(.*?)</tr>#s', $t, $zeilen);
            foreach ($zeilen[1] as $z) {
                if (!preg_match('#href="/ergebnisse/[a-z-]+/[a-z-]+/team-(\d+)"#', $z, $tid)) {
                    continue;
                }
                preg_match_all('#<t[hd][^>]*>(.*?)</t[hd]>#s', $z, $zellen);
                $c = array_map([self::class, 'text'], $zellen[1]);
                $teams[$c[2]] = $tid[1];
                $tabelle[] = ['platz' => (int)$c[0], 'name' => $c[2], 'spiele' => (int)$c[3], 's' => (int)$c[4], 'u' => 0, 'n' => (int)$c[5],
                    'diff' => $c[7], 'punkte' => (int)$c[6], 'eigen' => false];
            }
        }
        return ['liga' => $liga, 'spiele' => array_values($spiele), 'tabelle' => $tabelle, 'teams' => $teams];
    }

    private static function bvv(Verein $verein, bool $neuSuchen = false): array
    {
        $suche = (string)$verein->bfv_id;
        $erg = ['mannschaften' => [], 'spiele' => [], 'fehler' => [], 'log' => []];
        $ligen = self::bvvLigen($verein, $neuSuchen, $erg['log']);
        $seiten = [];
        foreach ($ligen as $pfad) {
            try {
                $seiten[$pfad] = self::bvvLiga(Bfv::holen(self::BVV . $pfad));
            } catch (\Throwable $e) {
                if (!$neuSuchen && preg_match('/HTTP 4(04|10)/', $e->getMessage())) {
                    return self::bvv($verein, true); // Saisonwechsel: Ligen neu suchen
                }
                $erg['log'][] = "! $pfad: " . $e->getMessage();
                $erg['ligaFehler'] = true;
            }
            self::pause();
        }
        // Erst die Ligen, dann die Pokale (deren Spiele gehören zur Mannschaft gleichen Namens)
        uksort($seiten, fn($a, $b) => [(int)preg_match('/pokal/i', $seiten[$a]['liga']), $a] <=> [(int)preg_match('/pokal/i', $seiten[$b]['liga']), $b]);
        $nachName = [];
        foreach ($seiten as $pfad => $s) {
            $pokal = (bool)preg_match('/pokal/i', $s['liga']);
            $typ = self::typAus($s['liga'], 'Herren', 'Frauen');
            $eigene = [];
            foreach (array_merge(array_column($s['spiele'], 'heim'), array_column($s['spiele'], 'gast')) as $name) {
                if (self::bvvIstEigen($name, $suche)) {
                    $eigene[$name] = true;
                }
            }
            foreach (array_keys($eigene) as $name) {
                $schluessel = self::bvvNorm($name) . '|' . $typ;
                if ($pokal && isset($nachName[$schluessel])) {
                    $id = $nachName[$schluessel];
                } else {
                    $id = 'bvv:' . ($s['teams'][$name] ?? substr(md5($pfad . $name), 0, 12));
                    $tabelle = $s['tabelle'];
                    foreach ($tabelle as &$z) {
                        $z['eigen'] = $z['name'] === $name;
                    }
                    unset($z);
                    $erg['mannschaften'][$id] = ['name' => $name, 'typ' => $typ, 'liga' => $pokal ? null : $s['liga'],
                        'liga_id' => $pokal ? null : basename($pfad), 'tabelle' => $pokal ? null : $tabelle];
                    $nachName[$schluessel] ??= $id;
                }
                foreach ($s['spiele'] as $sp) {
                    if ($sp['heim'] === $name || $sp['gast'] === $name) {
                        $erg['spiele'][$id][] = $sp + ['wettbewerb' => $s['liga'], 'wettbewerb_typ' => $pokal ? 'Pokale' : 'Meisterschaften',
                            'heim_eigen' => $sp['heim'] === $name];
                    }
                }
            }
        }
        $erg['ort'] = function (VereinSpiel $s): ?string {
            $html = Bfv::holen($s->url);
            // Vor „Halle“ steht teils ein Symbol (svg) in der Überschrift
            if (!preg_match('#Halle\s*</h3>\s*<div>\s*<p>(.*?)</p>#s', $html, $m)) {
                return '';
            }
            return implode(' | ', array_filter(array_map([self::class, 'text'], preg_split('#<br\s*/?>#', $m[1]))));
        };
        return $erg;
    }

    // ---------- Badminton (BBV) ----------

    private static function bbvSeite(string $club, string $teil): string
    {
        return Bfv::holen(self::BBV . '/liga2/backend/ajax.php', http_build_query(['path' => "/liga/club/BBV/$club/$teil"]));
    }

    private static function bbv(Verein $verein): array
    {
        $club = (string)$verein->bfv_id;
        $erg = ['mannschaften' => [], 'spiele' => [], 'fehler' => [], 'log' => []];
        // Mannschaften: Name und Liga (Mannschaftsführer und Telefonnummern bleiben dort)
        $html = self::bbvSeite($club, 'teams');
        preg_match_all('#<tr>(.*?)</tr>#s', $html, $zeilen);
        foreach ($zeilen[1] as $z) {
            // Spalte „Mannschaft“ ist die Art („Aktive“, „U17“ …), der Name steht bei den Spielen
            if (preg_match('#href="/liga/team/(\d+)/[^"]*">(.*?)</a>#s', $z, $t) && preg_match('#href="/liga/liga/([\d/]+)">(.*?)</a>#s', $z, $l)) {
                $id = 'bbv:' . $t[1];
                $liga = self::text($l[2]);
                $erg['mannschaften'][$id] = ['name' => $verein->bfv_name ?: $verein->name, 'typ' => self::typAus(self::text($t[2]) . ' ' . $liga, 'Herren', 'Damen'),
                    'liga' => $liga, 'liga_id' => $l[1], 'tabelle' => null];
            }
        }
        if (!$erg['mannschaften']) {
            throw new \RuntimeException('Keine Mannschaften gefunden');
        }
        self::pause();
        // Spiele mit Halle (die Seite zeigt die Liste zweimal: für große und kleine Bildschirme). Kennung aus dem
        // Kalender-Link, den auch kommende Spiele haben („Details“ gibt es erst nach dem Spiel)
        $html = self::bbvSeite($club, 'meetings');
        preg_match_all('#<tr>(.*?)</tr>#s', $html, $zeilen);
        $gesehen = [];
        foreach ($zeilen[1] as $z) {
            if (!preg_match('#ics\.php\?meeting=(\d+)#', $z, $mt) || isset($gesehen[$mt[1]])
                || !preg_match('#(\d{2})\.(\d{2})\.(\d{4})#', $z, $d)) {
                continue;
            }
            $mt[2] = $mt[1];
            preg_match_all('#href="/liga/team/(\d+)/[^"]*">(.*?)</a>#s', $z, $teams, PREG_SET_ORDER);
            if (count($teams) < 2) {
                continue;
            }
            $gesehen[$mt[2]] = true;
            $eigenHeim = isset($erg['mannschaften']['bbv:' . $teams[0][1]]);
            $id = 'bbv:' . ($eigenHeim ? $teams[0][1] : $teams[1][1]);
            if (!isset($erg['mannschaften'][$id])) {
                continue;
            }
            $erg['mannschaften'][$id]['name'] = self::text($teams[$eigenHeim ? 0 : 1][2]);
            $mt[1] = $erg['mannschaften'][$id]['liga_id'];
            preg_match_all('#<td class="[^"]*score[^"]*">\s*(\d+)\s*</td>#', $z, $tore);
            $erg['spiele'][$id][] = [
                'spiel_id' => $mt[2],
                'datum' => "$d[3]-$d[2]-$d[1]",
                'uhrzeit' => preg_match('#>(\d{2}:\d{2})<#', $z, $u) ? $u[1] : null,
                'wettbewerb' => $erg['mannschaften'][$id]['liga'],
                'wettbewerb_typ' => 'Meisterschaften',
                'heim' => self::text($teams[0][2]),
                'gast' => self::text($teams[1][2]),
                'heim_eigen' => $eigenHeim,
                'ergebnis' => count($tore[1]) >= 2 ? $tore[1][0] . ':' . $tore[1][1] : null,
                'url' => self::BBV . "/liga/liga/{$mt[1]}/meeting/{$mt[2]}",
                'ort' => preg_match('#href="/liga/hall/\d+">(.*?)</a>#s', $z, $h) ? self::text($h[1]) : null,
            ];
        }
        self::pause();
        // Tabellen: je Liga eine; die mit der eigenen Mannschaft gehört zu ihr
        try {
            $html = self::bbvSeite($club, 'tables');
            foreach (preg_split('#<table#', $html) as $tab) {
                if (!str_contains($tab, 'gespielt')) {
                    continue;
                }
                preg_match_all('#<tr>(.*?)</tr>#s', $tab, $zeilen);
                $zeilenTab = [];
                $fuer = null;
                foreach ($zeilen[1] as $z) {
                    if (!preg_match('#href="/liga/team/(\d+)/[^"]*">(.*?)</a>#s', $z, $t)) {
                        continue;
                    }
                    preg_match_all('#<td[^>]*>(.*?)</td>#s', $z, $zellen);
                    $c = array_map([self::class, 'text'], $zellen[1]);
                    // Platz, Team, gespielt, Punkte a : b, GEW, REM, VER, Spiele a : b, …
                    $eigen = isset($erg['mannschaften']['bbv:' . $t[1]]);
                    $fuer = $eigen ? 'bbv:' . $t[1] : $fuer;
                    $zeilenTab[] = ['platz' => (int)$c[0], 'name' => self::text($t[2]), 'spiele' => (int)$c[2], 's' => (int)$c[6], 'u' => (int)$c[7],
                        'n' => (int)$c[8], 'diff' => $c[9] . ':' . $c[11], 'punkte' => (int)$c[3], 'eigen' => $eigen];
                }
                if ($fuer && $erg['mannschaften'][$fuer]['tabelle'] === null) {
                    $erg['mannschaften'][$fuer]['tabelle'] = $zeilenTab;
                }
            }
        } catch (\Throwable $e) {
            $erg['log'][] = '! Tabellen: ' . $e->getMessage();
        }
        return $erg;
    }

    // ---------- Speichern ----------

    /**
     * Ergebnis einer Quelle mit der Datenbank abgleichen. Mannschaften, die die Quelle nicht mehr führt, werden samt
     * Spielen entfernt – außer ein Teil des Abrufs ist gescheitert, dann bleibt alles Bisherige stehen.
     */
    public static function speichern(Verein $verein, array $erg): array
    {
        $log = $erg['log'];
        $jetzt = date('Y-m-d H:i:s');
        $mannschaften = $erg['mannschaften'];
        // Gleiche Bezeichnung (z. B. zweimal „Frauen“ in verschiedenen Ligen): Liga als Zusatz
        $labels = [];
        foreach ($mannschaften as $id => $m) {
            $tmp = new VereinMannschaft(['id' => $id, 'name' => $m['name'], 'typ' => $m['typ']]);
            $labels[$tmp->getLabel()][] = $id;
        }
        foreach ($labels as $ids) {
            foreach ($ids as $id) {
                $mannschaften[$id]['zusatz'] = count($ids) > 1 ? self::stufe($mannschaften[$id]['liga']) : null;
            }
        }
        foreach ($mannschaften as $id => $m) {
            $mannschaft = VereinMannschaft::findOne($id) ?? new VereinMannschaft(['id' => $id]);
            $mannschaft->setAttributes([
                'verein_id' => $verein->id,
                'name' => mb_substr($m['name'], 0, 150),
                'typ' => mb_substr($m['typ'], 0, 50),
                'zusatz' => $m['zusatz'] ? mb_substr($m['zusatz'], 0, 60) : null,
                'liga' => $m['liga'] ? mb_substr($m['liga'], 0, 150) : null,
                'liga_id' => $m['liga_id'] ? mb_substr($m['liga_id'], 0, 60) : null,
                'tabelle' => $m['tabelle'] ? json_encode($m['tabelle'], JSON_UNESCAPED_UNICODE) : null,
                'aktualisiert_am' => $jetzt,
            ], false);
            $mannschaft->save(false);
            $spielIds = [];
            foreach ($erg['spiele'][$id] ?? [] as $s) {
                $spielIds[] = $s['spiel_id'];
                $spiel = VereinSpiel::findOne(['mannschaft_id' => $id, 'spiel_id' => $s['spiel_id']])
                    ?? new VereinSpiel(['mannschaft_id' => $id, 'spiel_id' => $s['spiel_id']]);
                $spiel->setAttributes([
                    'datum' => $s['datum'],
                    'uhrzeit' => $s['uhrzeit'],
                    'wettbewerb' => $s['wettbewerb'] ? mb_substr($s['wettbewerb'], 0, 150) : null,
                    'wettbewerb_typ' => $s['wettbewerb_typ'],
                    'heim' => mb_substr($s['heim'], 0, 150) ?: '?',
                    'gast' => mb_substr($s['gast'], 0, 150) ?: '?',
                    'heim_eigen' => (int)$s['heim_eigen'],
                    'ergebnis' => $s['ergebnis'] ? mb_substr($s['ergebnis'], 0, 30) : null,
                    'details' => isset($s['details']) && $s['details'] ? mb_substr($s['details'], 0, 120) : null,
                    'url' => mb_substr($s['url'], 0, 255),
                    'aktualisiert_am' => $jetzt,
                ], false);
                if (array_key_exists('ort', $s) && $s['ort'] !== null) {
                    $spiel->ort = mb_substr($s['ort'], 0, 255);
                }
                $spiel->save(false);
            }
            $weg = empty($erg['ligaFehler'])
                ? VereinSpiel::deleteAll(['and', ['mannschaft_id' => $id], ['not in', 'spiel_id', $spielIds ?: ['-']]]) : 0;
            $log[] = sprintf('%s (%s): %d Spiele%s', $m['name'], $mannschaft->getLabel(), count($spielIds), $weg ? ", $weg entfernt" : '');
        }
        if (empty($erg['ligaFehler'])) {
            foreach (VereinMannschaft::find()->where(['verein_id' => $verein->id])->andWhere(['not in', 'id', array_keys($mannschaften) ?: ['-']])->all() as $alt) {
                $log[] = "- {$alt->name} ({$alt->typ}) nicht mehr beim Verein";
                $alt->delete();
            }
        }
        if (isset($erg['ort'])) {
            $log[] = 'Hallen nachgeschlagen: ' . self::orteNachschlagen($verein, $erg['ort']);
        }
        return $log;
    }

    /** Spielklasse aus dem Liganamen: „UFR Bezirksklasse Herren Ost“ → „Bezirksklasse“, ohne Liga „Pokal“ */
    public static function stufe(?string $liga): string
    {
        if ($liga === null) {
            return 'Pokal';
        }
        return preg_match('/\b(\w*(?:liga|klasse))\b/ui', $liga, $m) ? $m[1] : (strtok($liga, ' ') ?: $liga);
    }

    /** Fehlende Hallen kommender Spiele nachschlagen; liefert die Anzahl */
    private static function orteNachschlagen(Verein $verein, callable $ort): int
    {
        $n = 0;
        $spiele = VereinSpiel::find()->innerJoinWith('mannschaft m', false)->where(['m.verein_id' => $verein->id, 'ort' => null])
            ->andWhere(['between', 'datum', date('Y-m-d'), date('Y-m-d', strtotime('+' . self::ORT_TAGE . ' days'))])
            ->orderBy(['datum' => SORT_ASC])->limit(self::ORT_SEITEN)->all();
        foreach ($spiele as $spiel) {
            try {
                $o = $ort($spiel);
                if ($o !== null) {
                    // Dasselbe Spiel kann bei zwei eigenen Mannschaften stehen
                    VereinSpiel::updateAll(['ort' => mb_substr($o, 0, 255)], ['spiel_id' => $spiel->spiel_id, 'url' => $spiel->url]);
                    $n++;
                }
            } catch (\Throwable $e) {
                Yii::warning("Halle {$spiel->spiel_id}: " . $e->getMessage(), 'vereine');
            }
            self::pause();
        }
        return $n;
    }
}
