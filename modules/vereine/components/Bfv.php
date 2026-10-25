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
        return $log;
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
                if ($s->getTore() !== null) {
                    $text .= ' ' . $s->ergebnis;
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
