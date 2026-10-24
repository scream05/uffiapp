<?php

namespace humhub\modules\oeffnungszeiten\components;

use humhub\modules\oeffnungszeiten\Module;
use Yii;
use yii\helpers\Url;

/** Berechnet „jetzt geöffnet“, die nächste Öffnung und die Zeilen für die Anzeige */
class Zeiten
{
    public const TAGE = [1 => 'Montag', 2 => 'Dienstag', 3 => 'Mittwoch', 4 => 'Donnerstag', 5 => 'Freitag', 6 => 'Samstag', 7 => 'Sonntag'];
    public const KURZ = [1 => 'Mo.', 2 => 'Di.', 3 => 'Mi.', 4 => 'Do.', 5 => 'Fr.', 6 => 'Sa.', 7 => 'So.'];

    /** Bald schließend: so viele Minuten vor Schluss */
    public const BALD = 30;

    public static function modul(): Module
    {
        return Yii::$app->getModule('oeffnungszeiten');
    }

    /** Führt $f in Uffenheimer Ortszeit aus (HumHub stellt sonst die Zeitzone des Kontos ein) */
    public static function ortszeit(callable $f): mixed
    {
        $alt = date_default_timezone_get();
        date_default_timezone_set('Europe/Berlin');
        try {
            return $f();
        } finally {
            date_default_timezone_set($alt);
        }
    }

    /** Gesetzliche Feiertage in Bayern für Uffenheim (überwiegend evangelisch: ohne Mariä Himmelfahrt) => Name */
    public static function feiertage(int $jahr): array
    {
        // Ostersonntag (Gauß, gregorianisch); easter_date() fehlt im Image
        $a = $jahr % 19; $b = intdiv($jahr, 100); $c = $jahr % 100; $d = intdiv($b, 4); $e = $b % 4;
        $f = intdiv($b + 8, 25); $g = intdiv($b - $f + 1, 3); $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4); $k = $c % 4; $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7; $m = intdiv($a + 11 * $h + 22 * $l, 451);
        $monat = intdiv($h + $l - 7 * $m + 114, 31); $tag = (($h + $l - 7 * $m + 114) % 31) + 1;
        $ostern = mktime(12, 0, 0, $monat, $tag, $jahr);
        $o = fn(int $tage) => date('Y-m-d', $ostern + $tage * 86400);
        return [
            "$jahr-01-01" => 'Neujahr', "$jahr-01-06" => 'Heilige Drei Könige', $o(-2) => 'Karfreitag', $o(1) => 'Ostermontag',
            "$jahr-05-01" => 'Tag der Arbeit', $o(39) => 'Christi Himmelfahrt', $o(50) => 'Pfingstmontag', $o(60) => 'Fronleichnam',
            "$jahr-10-03" => 'Tag der Deutschen Einheit', "$jahr-11-01" => 'Allerheiligen',
            "$jahr-12-25" => '1. Weihnachtstag', "$jahr-12-26" => '2. Weihnachtstag',
        ];
    }

    public static function feiertag(string $datum): ?string
    {
        return self::feiertage((int)substr($datum, 0, 4))[$datum] ?? null;
    }

    /** Liegt der Tag in einer Saison? (ohne Saisonangabe immer) */
    public static function inSaison(array $ort, string $datum): bool
    {
        foreach (self::saisons($ort, (int)substr($datum, 0, 4)) as [$von, $bis]) {
            if (($von === null || $datum >= $von) && ($bis === null || $datum <= $bis)) {
                return true;
            }
        }
        return !isset($ort['saisons']);
    }

    private static function saisons(array $ort, int $jahr): array
    {
        $s = $ort['saisons'] ?? [];
        return is_callable($s) ? [$s($jahr)] : $s;
    }

    /** Zeiten an einem Tag (ohne Feiertag/Saison) */
    public static function regulaer(array $ort, int $ts): array
    {
        $wt = (int)date('N', $ts);
        if (isset($ort['ersterImMonat'][$wt]) && (int)date('j', $ts) <= 7) {
            return $ort['ersterImMonat'][$wt];
        }
        return $ort['zeiten'][$wt] ?? [];
    }

    /** Tatsächliche Zeiten an einem Tag als [[von-ts, bis-ts], …] */
    public static function anTag(array $ort, int $ts): array
    {
        $datum = date('Y-m-d', $ts);
        if (!self::inSaison($ort, $datum) || (!empty($ort['feiertagZu']) && self::feiertag($datum) !== null)
            || isset($ort['zuAn'][substr($datum, 5)])) {
            return [];
        }
        return array_map(fn($z) => [strtotime("$datum {$z[0]}"), strtotime("$datum {$z[1]}")], self::regulaer($ort, $ts));
    }

    /**
     * Zustand zu einem Zeitpunkt:
     * ['offen' => bool, 'bald' => bool, 'text' => übersetzter Kurztext, 'saison' => false wenn gerade keine Saison ist]
     */
    public static function status(array $ort, ?int $jetzt = null): array
    {
        $jetzt ??= time();
        $heute = date('Y-m-d', $jetzt);
        foreach (self::anTag($ort, $jetzt) as [$von, $bis]) {
            if ($jetzt >= $von && $jetzt < $bis) {
                return ['offen' => true, 'bald' => $bis - $jetzt <= self::BALD * 60, 'saison' => true, 'ab' => $von,
                    'text' => Yii::t('OeffnungszeitenModule.base', 'geöffnet bis {zeit} Uhr', ['zeit' => date('G:i', $bis)])];
            }
        }
        $saison = self::inSaison($ort, $heute);
        for ($t = 0; $t <= 14; $t++) {
            $tag = strtotime("$heute +$t days 12:00");
            foreach (self::anTag($ort, $tag) as [$von]) {
                if ($von <= $jetzt) {
                    continue;
                }
                $zeit = date('G:i', $von);
                $text = match (true) {
                    $t === 0 => Yii::t('OeffnungszeitenModule.base', 'öffnet um {zeit} Uhr', ['zeit' => $zeit]),
                    $t === 1 => Yii::t('OeffnungszeitenModule.base', 'öffnet morgen um {zeit} Uhr', ['zeit' => $zeit]),
                    default => Yii::t('OeffnungszeitenModule.base', 'öffnet {tag} um {zeit} Uhr',
                        ['tag' => Yii::t('OeffnungszeitenModule.base', self::TAGE[(int)date('N', $von)]), 'zeit' => $zeit]),
                };
                return ['offen' => false, 'bald' => false, 'saison' => $saison, 'ab' => $von, 'text' => $text];
            }
        }
        if (!$saison) {
            return ['offen' => false, 'bald' => false, 'saison' => false, 'ab' => PHP_INT_MAX, 'text' => Yii::t('OeffnungszeitenModule.base', 'Saisonpause')];
        }
        return ['offen' => false, 'bald' => false, 'saison' => true, 'ab' => PHP_INT_MAX, 'text' => Yii::t('OeffnungszeitenModule.base', 'geschlossen')];
    }

    /**
     * Anzeigezeilen, Tage mit gleichen Zeiten zusammengefasst: [['Mo.–Fr.', '8:00–12:00 Uhr', heute?], …]
     * „heute“ markiert die Zeile, die am Tag $ts gilt (am 1. Donnerstag im Monat die Sonderzeile).
     */
    public static function zeilen(array $ort, ?int $ts = null): array
    {
        $ts ??= time();
        $wt = (int)date('N', $ts);
        $sonderHeute = isset($ort['ersterImMonat'][$wt]) && (int)date('j', $ts) <= 7;
        $gruppen = [];
        foreach ($ort['zeiten'] as $tag => $zeiten) {
            $gruppen[self::zeitText($zeiten)][] = $tag;
        }
        $zeilen = [];
        foreach ($gruppen as $zeit => $tage) {
            $zeilen[] = [self::tageText($tage), $zeit, !$sonderHeute && in_array($wt, $tage, true)];
        }
        foreach ($ort['ersterImMonat'] ?? [] as $tag => $zeiten) {
            $zeilen[] = [Yii::t('OeffnungszeitenModule.base', 'am 1. {tag} im Monat', ['tag' => Yii::t('OeffnungszeitenModule.base', self::TAGE[$tag])]),
                self::zeitText($zeiten), $sonderHeute && $tag === $wt];
        }
        return $zeilen;
    }

    public static function zeitText(array $zeiten): string
    {
        $teile = array_map(fn($z) => ltrim($z[0], '0') . '–' . ltrim($z[1], '0'), $zeiten);
        return Yii::t('OeffnungszeitenModule.base', '{zeiten} Uhr', ['zeiten' => implode(', ', $teile)]);
    }

    /** [1,2,3,4,5] => „Mo.–Fr.“, [1,2,4] => „Mo., Di., Do.“ */
    public static function tageText(array $tage): string
    {
        sort($tage);
        $laeufe = [];
        foreach ($tage as $wt) {
            $n = count($laeufe);
            if ($n && end($laeufe[$n - 1]) === $wt - 1) {
                $laeufe[$n - 1][] = $wt;
            } else {
                $laeufe[] = [$wt];
            }
        }
        $kurz = fn(int $wt) => Yii::t('OeffnungszeitenModule.base', self::KURZ[$wt]);
        $teile = [];
        foreach ($laeufe as $lauf) {
            if (count($lauf) >= 3) {
                $teile[] = $kurz($lauf[0]) . '–' . $kurz(end($lauf));
            } else {
                foreach ($lauf as $wt) {
                    $teile[] = $kurz($wt);
                }
            }
        }
        return count($tage) === 7 ? Yii::t('OeffnungszeitenModule.base', 'täglich') : implode(', ', $teile);
    }

    /** „An Feiertagen geschlossen.“ samt festen Schließtagen (Heiligabend …) oder null */
    public static function geschlossenText(array $ort): ?string
    {
        $tage = array_map(fn($n) => Yii::t('OeffnungszeitenModule.base', $n), array_values($ort['zuAn'] ?? []));
        $letzter = array_pop($tage);
        $tage = $tage ? implode(', ', $tage) . ' ' . Yii::t('OeffnungszeitenModule.base', 'und') . ' ' . $letzter : $letzter;
        if (!empty($ort['feiertagZu'])) {
            return $tage
                ? Yii::t('OeffnungszeitenModule.base', 'An Feiertagen sowie an {tage} geschlossen.', ['tage' => $tage])
                : Yii::t('OeffnungszeitenModule.base', 'An Feiertagen geschlossen.');
        }
        return $tage ? Yii::t('OeffnungszeitenModule.base', '{tage} geschlossen.', ['tage' => $tage]) : null;
    }

    /** Saisonzeile für die Anzeige oder null */
    public static function saisonText(array $ort, ?int $jetzt = null): ?string
    {
        if (!isset($ort['saisons'])) {
            return null;
        }
        $jetzt ??= time();
        $jahr = (int)date('Y', $jetzt);
        $heute = date('Y-m-d', $jetzt);
        $d = fn(?string $datum) => $datum === null ? null : Yii::$app->formatter->asDate($datum, 'medium');
        foreach (array_merge(self::saisons($ort, $jahr), self::saisons($ort, $jahr + 1)) as [$von, $bis]) {
            if ($bis !== null && $bis < $heute) {
                continue;
            }
            if ($von !== null && $von > $heute) {
                return Yii::t('OeffnungszeitenModule.base', 'Saison {von} bis {bis}', ['von' => $d($von), 'bis' => $d($bis)]);
            }
            return $bis === null
                ? Yii::t('OeffnungszeitenModule.base', 'Saison seit {von}', ['von' => $d($von)])
                : Yii::t('OeffnungszeitenModule.base', 'Saison bis {bis}', ['bis' => $d($bis)]);
        }
        $letzte = null;
        foreach (self::saisons($ort, $jahr) as [, $bis]) {
            $letzte = $bis;
        }
        return Yii::t('OeffnungszeitenModule.base', 'Saison beendet{bis} – der neue Termin steht noch nicht fest.',
            ['bis' => $letzte ? ' (' . Yii::t('OeffnungszeitenModule.base', 'letzter Tag {datum}', ['datum' => $d($letzte)]) . ')' : '']);
    }

    /** Abschnitt für Uffi (deutsch, im 10-Minuten-Zwischenspeicher: nur feste Zeiten, Uffi rechnet mit der Uhrzeit) */
    public static function forAssistant(): string
    {
        return self::ortszeit(fn() => self::fuerUffi());
    }

    private static function fuerUffi(): string
    {
        $alt = Yii::$app->language;
        Yii::$app->language = 'de';
        try {
            $lines = [];
            foreach (self::modul()->orte() as $ort) {
                $teile = array_map(fn($z) => "{$z[0]} {$z[1]}", self::zeilen($ort));
                if (($s = self::saisonText($ort)) !== null) {
                    $teile[] = $s;
                }
                $teile[] = !empty($ort['feiertagZu']) ? 'an Feiertagen geschlossen' : '';
                $teile[] = !empty($ort['zuAn']) ? 'außerdem geschlossen: ' . implode(', ', $ort['zuAn']) : '';
                $lines[] = '- ' . $ort['name'] . (!empty($ort['adresse']) ? " ({$ort['adresse']})" : '')
                    . ': ' . implode('; ', array_filter($teile))
                    . (!empty($ort['telefon']) ? "; Tel. {$ort['telefon']}" : '')
                    . (!empty($ort['hinweis']) ? ". {$ort['hinweis']}" : '');
            }
            $feiertage = [];
            $heute = date('Y-m-d');
            foreach (self::feiertage((int)date('Y')) + self::feiertage((int)date('Y') + 1) as $datum => $name) {
                if ($datum >= $heute && count($feiertage) < 3) {
                    $feiertage[] = date('d.m.Y', strtotime($datum)) . " $name";
                }
            }
            return "## Öffnungszeiten städtischer Einrichtungen in Uffenheim (für „hat … gerade offen?“ mit der aktuellen Uhrzeit rechnen)\n"
                . 'Seite mit allen Zeiten und Anzeige „jetzt geöffnet“: ' . Url::to(['/oeffnungszeiten/index'], true) . "\n"
                . implode("\n", $lines) . "\n"
                . 'Nächste Feiertage: ' . implode(', ', $feiertage);
        } finally {
            Yii::$app->language = $alt;
        }
    }
}
