<?php

namespace humhub\modules\gewerbe\components;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Öffnungszeiten je Wochentag: [1 => [['08:00', '12:00'], ['14:00', '18:00']], …, 7 => []] (1 = Montag).
 * Zeiten über Mitternacht (z. B. 18:00–01:00) zählen bis in den nächsten Tag.
 */
class Oeffnungszeiten
{
    public const TAGE = [1 => 'Montag', 2 => 'Dienstag', 3 => 'Mittwoch', 4 => 'Donnerstag', 5 => 'Freitag', 6 => 'Samstag', 7 => 'Sonntag'];
    public const KURZ = [1 => 'Mo', 2 => 'Di', 3 => 'Mi', 4 => 'Do', 5 => 'Fr', 6 => 'Sa', 7 => 'So'];

    public static function ausJson(?string $json): array
    {
        $data = $json ? json_decode($json, true) : null;
        $zeiten = [];
        foreach (self::TAGE as $tag => $_) {
            $zeiten[$tag] = [];
            foreach ((array)($data[$tag] ?? $data[(string)$tag] ?? []) as $spanne) {
                if (is_array($spanne) && count($spanne) === 2 && self::gueltig($spanne[0]) && self::gueltig($spanne[1])) {
                    $zeiten[$tag][] = [$spanne[0], $spanne[1]];
                }
            }
        }
        return $zeiten;
    }

    public static function gueltig($zeit): bool
    {
        return is_string($zeit) && preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $zeit) === 1;
    }

    public static function hatZeiten(array $zeiten): bool
    {
        return array_filter($zeiten) !== [];
    }

    private static function minuten(string $zeit): int
    {
        return (int)substr($zeit, 0, 2) * 60 + (int)substr($zeit, 3, 2);
    }

    /** „08:00“ → „8:00“ */
    public static function zeit(string $zeit): string
    {
        return (int)substr($zeit, 0, 2) . substr($zeit, 2);
    }

    /**
     * Stand zum Zeitpunkt $jetzt (Europe/Berlin).
     * @return array{offen: bool, text: string}|null null, wenn keine Zeiten hinterlegt sind
     */
    public static function stand(array $zeiten, ?DateTimeImmutable $jetzt = null): ?array
    {
        if (!self::hatZeiten($zeiten)) {
            return null;
        }
        $jetzt ??= new DateTimeImmutable('now', new DateTimeZone('Europe/Berlin'));
        $tag = (int)$jetzt->format('N');
        $min = (int)$jetzt->format('G') * 60 + (int)$jetzt->format('i');
        // Heute geöffnet, oder noch von gestern über Mitternacht?
        foreach ([[$tag, $min], [$tag === 1 ? 7 : $tag - 1, $min + 1440]] as [$t, $m]) {
            foreach ($zeiten[$t] as [$von, $bis]) {
                $a = self::minuten($von);
                $b = self::minuten($bis);
                if ($b <= $a) {
                    $b += 1440;
                }
                if ($m >= $a && $m < $b) {
                    return ['offen' => true, 'text' => $von === $bis ? 'Jetzt geöffnet · rund um die Uhr' : 'Jetzt geöffnet · bis ' . self::zeit($bis) . ' Uhr'];
                }
            }
        }
        // Nächste Öffnung in den kommenden 7 Tagen
        for ($i = 0; $i <= 7; $i++) {
            $t = ($tag - 1 + $i) % 7 + 1;
            foreach ($zeiten[$t] as [$von]) {
                if ($i === 0 && self::minuten($von) <= $min) {
                    continue;
                }
                $wann = $i === 0 ? 'heute' : ($i === 1 ? 'morgen' : self::KURZ[$t]);
                return ['offen' => false, 'text' => 'Geschlossen · öffnet ' . $wann . ' ' . self::zeit($von) . ' Uhr'];
            }
        }
        return ['offen' => false, 'text' => 'Geschlossen'];
    }

    /**
     * Öffnungszeiten im OpenStreetMap-Format („Mo-Fr 08:00-12:00,14:00-18:00; Sa 08:00-12:00; Su off“) in unser Format.
     * Nur die einfache Form (Tage, Uhrzeiten, off, 24/7); Feiertage (PH) werden übergangen. Alles andere (Monate,
     * Wochen, Kommentare, Sonnenzeiten …) → null, dann bleibt nur der Text als Hinweis.
     */
    public static function ausOsm(string $text): ?array
    {
        $kuerzel = ['Mo' => 1, 'Tu' => 2, 'We' => 3, 'Th' => 4, 'Fr' => 5, 'Sa' => 6, 'Su' => 7];
        $text = trim(preg_replace('/\s+/', ' ', $text));
        if ($text === '') {
            return null;
        }
        $zeiten = array_fill(1, 7, []);
        if ($text === '24/7') {
            return array_fill(1, 7, [['00:00', '00:00']]);
        }
        $text = str_replace('+', '', $text); // offenes Ende („24:00+“) wie angegebenes Ende behandeln
        foreach (array_filter(array_map('trim', explode(';', $text))) as $gruppe) {
            // Mit Komma angehängte Zusatzregeln („Mo-Fr 9-12, Sa 9-13“) ergänzen sich, mit Semikolon getrennte überschreiben
            $neu = [];
            foreach (preg_split('/(?<=\d|f),\s*(?=(?:Mo|Tu|We|Th|Fr|Sa|Su|PH)\b)/', $gruppe) as $regel) {
                $regel = trim($regel);
                if ($regel === '' || preg_match('/^PH\b/', $regel)) {
                    continue; // Feiertagsregeln („PH off“) übergehen
                }
                $r = self::osmRegel($regel, $kuerzel);
                if ($r === null) {
                    return null;
                }
                foreach ($r[0] as $tag) {
                    $neu[$tag] = array_merge($neu[$tag] ?? [], $r[1]);
                }
            }
            foreach ($neu as $tag => $spannen) {
                usort($spannen, fn($x, $y) => strcmp($x[0], $y[0]));
                $zeiten[$tag] = $spannen;
            }
        }
        return self::hatZeiten($zeiten) ? $zeiten : null;
    }

    /** Eine Regel „Tage Zeiten“ → [[Tage], [[von, bis], …]] oder null */
    private static function osmRegel(string $regel, array $kuerzel): ?array
    {
        if (!preg_match('/^(?:((?:Mo|Tu|We|Th|Fr|Sa|Su)(?:-(?:Mo|Tu|We|Th|Fr|Sa|Su))?(?:,(?:Mo|Tu|We|Th|Fr|Sa|Su)(?:-(?:Mo|Tu|We|Th|Fr|Sa|Su))?)*)(?:,PH)?\s*)?(off|closed|(?:\d{1,2}:\d{2}-\d{1,2}:\d{2}(?:,\s*\d{1,2}:\d{2}-\d{1,2}:\d{2})*))$/', $regel, $m)) {
            return null;
        }
        $tage = [];
        foreach ($m[1] !== '' ? explode(',', $m[1]) : ['Mo-Su'] as $teil) {
            [$a, $b] = array_pad(explode('-', $teil), 2, null);
            $von = $kuerzel[$a];
            $bis = $b !== null ? $kuerzel[$b] : $von;
            for ($i = 0, $t = $von; $i < 7; $i++, $t = $t % 7 + 1) {
                $tage[] = $t;
                if ($t === $bis) {
                    break;
                }
            }
        }
        $spannen = [];
        if (!in_array($m[2], ['off', 'closed'], true)) {
            foreach (explode(',', str_replace(' ', '', $m[2])) as $spanne) {
                [$a, $b] = explode('-', $spanne);
                $a = str_pad($a, 5, '0', STR_PAD_LEFT);
                $b = str_pad($b, 5, '0', STR_PAD_LEFT);
                $b = $b === '24:00' ? '00:00' : $b;
                if (!self::gueltig($a) || !self::gueltig($b)) {
                    return null;
                }
                $spannen[] = [$a, $b];
            }
        }
        return [array_values(array_unique($tage)), $spannen];
    }

    /** Zeilen zur Anzeige: Tage mit gleichen Zeiten zusammengefasst, z. B. ['Mo–Fr', '8:00–12:00, 14:00–18:00 Uhr'] */
    public static function zeilen(array $zeiten): array
    {
        $zeilen = [];
        $text = fn(array $s) => $s ? ($s[0][0] === $s[0][1] ? 'rund um die Uhr'
            : implode(', ', array_map(fn($p) => self::zeit($p[0]) . '–' . self::zeit($p[1]), $s)) . ' Uhr') : 'geschlossen';
        $start = 1;
        for ($t = 1; $t <= 7; $t++) {
            if ($t === 7 || $text($zeiten[$t + 1]) !== $text($zeiten[$t])) {
                $zeilen[] = [$start === $t ? self::KURZ[$t] : self::KURZ[$start] . '–' . self::KURZ[$t], $text($zeiten[$t])];
                $start = $t + 1;
            }
        }
        return $zeilen;
    }
}
