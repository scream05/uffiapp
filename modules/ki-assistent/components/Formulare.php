<?php

namespace humhub\modules\kiAssistent\components;

use Yii;

/**
 * Antragsformulare der Stadt ausfüllen: Beschreibung je Formular in data/formulare/<id>.json, Vorlage-PDF daneben.
 * Die Werte werden mit Ghostscript als Text auf die Vorlage gedruckt (PostScript-EndPage), damit funktionieren
 * ausfüllbare und „flache“ PDFs gleich. Koordinaten in Punkt, Ursprung oben links (wie pdftotext -bbox), y = Grundlinie.
 *
 * Fertige PDFs liegen nur kurz in @runtime/ki-formulare (je Nutzer, max. 1 Stunde) und werden nicht weiter gespeichert.
 */
class Formulare
{
    public const TTL = 3600;

    private static ?array $all = null;

    /** @return array id => Definition */
    public static function all(): array
    {
        if (self::$all === null) {
            self::$all = [];
            foreach (glob(self::dir() . '/*.json') as $file) {
                $def = json_decode(file_get_contents($file), true);
                if (is_array($def) && !empty($def['id'])) {
                    self::$all[$def['id']] = $def;
                }
            }
            ksort(self::$all);
        }
        return self::$all;
    }

    public static function get(string $id): ?array
    {
        return self::all()[$id] ?? null;
    }

    /** Kurzliste für den Systemprompt */
    public static function catalog(): string
    {
        return implode("\n", array_map(fn($f) => "- {$f['id']}: {$f['titel']} – {$f['zweck']}", self::all()));
    }

    /** Was die KI über ein Formular wissen muss: Ablauf und Felder (ohne Druckpositionen) */
    public static function info(array $def): array
    {
        return [
            'id' => $def['id'],
            'titel' => $def['titel'],
            'zweck' => $def['zweck'],
            'ablauf' => $def['ablauf'],
            'quelle' => $def['quelle'],
            'felder' => array_map(fn($f) => array_filter([
                'name' => $f['name'],
                'frage' => $f['label'],
                'typ' => $f['typ'] ?? 'text',
                'pflicht' => !empty($f['pflicht']) ?: null,
                'optionen' => $f['optionen'] ?? null,
                'hinweis' => $f['hinweis'] ?? null,
                'nur_wenn' => $f['nur_wenn'] ?? (isset($f['nur_wenn_gesetzt']) ? "nur wenn {$f['nur_wenn_gesetzt']} angegeben" : null),
            ], fn($v) => $v !== null), $def['felder']),
        ];
    }

    /**
     * Werte prüfen und normalisieren.
     * @return array [werte, fehlend (Feldnamen), fehler (Texte)]
     */
    public static function validate(array $def, array $input): array
    {
        $values = [];
        $missing = [];
        $errors = [];
        foreach ($def['felder'] as $f) {
            $name = $f['name'];
            $v = trim(preg_replace('/\s+/u', ' ', (string)($input[$name] ?? '')));
            $v = mb_substr($v, 0, (int)($f['max'] ?? 200));
            if (isset($f['nur_wenn']) && !self::conditionMet($f['nur_wenn'], $values)
                || isset($f['nur_wenn_gesetzt']) && ($values[$f['nur_wenn_gesetzt']] ?? '') === '') {
                continue;
            }
            if ($v === '') {
                if (!empty($f['pflicht'])) {
                    $missing[] = $name;
                }
                continue;
            }
            if (isset($f['muster']) && !preg_match('/^(?:' . $f['muster'] . ')$/u', $v)) {
                $errors[] = "{$f['label']}: " . ($f['muster_hinweis'] ?? 'ungültiges Format');
                continue;
            }
            switch ($f['typ'] ?? 'text') {
                case 'datum':
                    $d = self::date($v);
                    if ($d === null) {
                        $errors[] = "{$f['label']}: bitte als Datum TT.MM.JJJJ angeben";
                        continue 2;
                    }
                    $v = $d;
                    break;
                case 'auswahl':
                    $match = null;
                    foreach ($f['optionen'] as $o) {
                        if (mb_strtolower($o) === mb_strtolower($v)) {
                            $match = $o;
                        }
                    }
                    if ($match === null) {
                        $errors[] = "{$f['label']}: erlaubt sind " . implode(', ', $f['optionen']);
                        continue 2;
                    }
                    $v = $match;
                    break;
                case 'mehrfach':
                    $chosen = [];
                    foreach (preg_split('/\s*[,;]\s*/u', $v) as $part) {
                        foreach ($f['optionen'] as $o) {
                            if (mb_strtolower($o) === mb_strtolower($part)) {
                                $chosen[] = $o;
                            }
                        }
                    }
                    if (!$chosen) {
                        $errors[] = "{$f['label']}: erlaubt sind " . implode(', ', $f['optionen']);
                        continue 2;
                    }
                    $v = implode('; ', array_unique($chosen));
                    break;
                case 'zahl':
                    if (!preg_match('/^\d{1,4}$/', $v)) {
                        $errors[] = "{$f['label']}: bitte eine Zahl angeben";
                        continue 2;
                    }
                    break;
                case 'plz':
                    if (!preg_match('/^\d{5}$/', $v)) {
                        $errors[] = "{$f['label']}: bitte eine fünfstellige Postleitzahl angeben";
                        continue 2;
                    }
                    break;
            }
            $values[$name] = $v;
        }
        return [$values, $missing, $errors];
    }

    /** PDF erzeugen; gibt einen Download-Schlüssel zurück, der nur für den aktuellen Nutzer gilt */
    public static function render(array $def, array $values): string
    {
        self::cleanup();
        $values += ['heute' => date('d.m.Y')];
        // Abgeleitete Werte, z. B. {"name": "urk_auszug", "wenn": {"urkunden": ["Geburtsregister-Auszug", …]}} → „ja“
        foreach ($def['ableiten'] ?? [] as $a) {
            if (self::conditionMet($a['wenn'], $values)) {
                $values[$a['name']] = $a['wert'] ?? 'ja';
            }
        }
        $pages = [];
        foreach ($def['druck'] as $item) {
            foreach (self::drawOps($item, $values) as [$page, $ps]) {
                $pages[$page][] = $ps;
            }
        }
        $ps = self::prolog() . "/FillPage {\n";
        foreach ($pages as $page => $ops) {
            $ps .= "dup " . ($page - 1) . " eq {\n" . implode("\n", $ops) . "\n} if\n";
        }
        $ps .= "pop } def\n<< /EndPage { exch /OvCnt exch def dup 0 eq { gsave OvCnt FillPage grestore } if 2 ne } >> setpagedevice\n";

        $dir = Yii::getAlias('@runtime/ki-formulare');
        @mkdir($dir, 0770, true);
        $token = bin2hex(random_bytes(16));
        $psFile = "$dir/$token.ps";
        $pdfFile = "$dir/$token.pdf";
        file_put_contents($psFile, $ps);
        $cmd = ['gs', '-q', '-dSAFER', '-dBATCH', '-dNOPAUSE', '-sDEVICE=pdfwrite', '-dPreserveAnnots=false',
            '-o', $pdfFile, $psFile, '-f', self::dir() . '/' . $def['vorlage']];
        $proc = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        $err = stream_get_contents($pipes[2]);
        stream_get_contents($pipes[1]);
        $code = proc_close($proc);
        @unlink($psFile);
        if ($code !== 0 || !is_file($pdfFile)) {
            @unlink($pdfFile);
            throw new \RuntimeException('PDF konnte nicht erzeugt werden: ' . mb_substr($err, 0, 200));
        }
        file_put_contents("$dir/$token.json", json_encode([
            'user' => self::besitzer(),
            'name' => $def['dateiname'] ?? $def['id'],
        ]));
        return $token;
    }

    /** @return array|null [Dateipfad, Dateiname] für den Download, nur für den Nutzer, der es erzeugt hat */
    public static function file(string $token): ?array
    {
        if (!preg_match('/^[0-9a-f]{32}$/', $token)) {
            return null;
        }
        $dir = Yii::getAlias('@runtime/ki-formulare');
        $meta = json_decode((string)@file_get_contents("$dir/$token.json"), true);
        $pdf = "$dir/$token.pdf";
        if (!$meta || !is_file($pdf) || filemtime($pdf) < time() - self::TTL || $meta['user'] !== self::besitzer()) {
            return null;
        }
        return [$pdf, $meta['name'] . '-' . date('Y-m-d') . '.pdf'];
    }

    /** Konto-ID, bei Gästen die Browser-Sitzung (gehasht), damit nur der Ersteller das PDF abrufen kann */
    private static function besitzer(): int|string|null
    {
        if (!Yii::$app->has('user')) {
            return null;
        }
        if (!Yii::$app->user->isGuest) {
            return Yii::$app->user->id;
        }
        Yii::$app->session->open();
        return 'gast-' . hash('sha256', Yii::$app->session->id);
    }

    /** Abgelaufene PDFs löschen */
    public static function cleanup(): void
    {
        foreach (glob(Yii::getAlias('@runtime/ki-formulare') . '/*') ?: [] as $f) {
            if (filemtime($f) < time() - self::TTL) {
                @unlink($f);
            }
        }
    }

    public static function dir(): string
    {
        return dirname(__DIR__) . '/data/formulare';
    }

    // ---------------------------------------------------------------------------------------------------------------

    private static function conditionMet(array $cond, array $values): bool
    {
        foreach ($cond as $field => $allowed) {
            $v = $values[$field] ?? '';
            $parts = preg_split('/;\s*/u', $v);
            if (!array_intersect((array)$allowed, $parts)) {
                return false;
            }
        }
        return true;
    }

    private static function date(string $v): ?string
    {
        if (preg_match('/^(\d{1,2})\.(\d{1,2})\.(\d{2}|\d{4})$/', $v, $m)) {
            [$d, $mo, $y] = [(int)$m[1], (int)$m[2], (int)$m[3]];
        } elseif (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $v, $m)) {
            [$y, $mo, $d] = [(int)$m[1], (int)$m[2], (int)$m[3]];
        } else {
            return null;
        }
        if ($y < 100) {
            $y += $y > (int)date('y') + 5 ? 1900 : 2000;
        }
        return checkdate($mo, $d, $y) ? sprintf('%02d.%02d.%04d', $d, $mo, $y) : null;
    }

    /**
     * Druckbefehle für einen Eintrag aus „druck“:
     *  {feld, seite, x, y, breite?, groesse?}            Text (wird bei Bedarf kleiner gesetzt)
     *  {feld, wert, seite, x, y}                          Kreuz, wenn feld == wert (bei „mehrfach“: enthalten)
     *  {text, seite, x, y}                                fester Text, {feld} wird ersetzt
     *  {feld, seite, zeilen: [y…], spalten: [{x, breite}…]}  Liste „A, B; C, D“ → Zeilen/Spalten
     *  {weiss: [x, y, breite, hoehe], seite}              Fläche weiß überdecken (Vorbelegung im Original)
     * @return array [[seite, PostScript], …]
     */
    private static function drawOps(array $item, array $values): array
    {
        $page = (int)($item['seite'] ?? 1);
        $h = 841.92;
        if (isset($item['wenn']) && !self::conditionMet($item['wenn'], $values)) {
            return [];
        }
        if (isset($item['weiss'])) {
            [$x, $y, $w, $hh] = $item['weiss'];
            return [[$page, sprintf('1 setgray %.1f %.1f %.1f %.1f rectfill 0 0 0.55 setrgbcolor', $x, $h - $y - $hh, $w, $hh)]];
        }
        if (isset($item['text'])) {
            // Nur drucken, wenn alle Platzhalter einen Wert haben
            preg_match_all('/\{(\w+)\}/', $item['text'], $m);
            foreach ($m[1] as $name) {
                if (($values[$name] ?? '') === '') {
                    return [];
                }
            }
            $text = preg_replace_callback('/\{(\w+)\}/', fn($m) => $values[$m[1]], $item['text']);
            return [[$page, self::textOp($text, $item['x'], $h - $item['y'], $item['breite'] ?? 300, $item['groesse'] ?? 10)]];
        }
        $v = $values[$item['feld']] ?? null;
        if ($v === null || $v === '') {
            return [];
        }
        if (array_key_exists('wert', $item)) {
            $hit = in_array($item['wert'], preg_split('/;\s*/u', $v), true);
            return $hit ? [[$page, self::textOp('X', $item['x'], $h - $item['y'], 20, $item['groesse'] ?? 11)]] : [];
        }
        if (isset($item['zeilen'])) {
            $ops = [];
            $rows = array_values(array_filter(array_map('trim', preg_split('/;/u', $v))));
            foreach (array_slice($rows, 0, count($item['zeilen'])) as $r => $row) {
                $cols = array_map('trim', explode(',', $row, count($item['spalten'])));
                foreach ($item['spalten'] as $c => $col) {
                    if (($cols[$c] ?? '') !== '') {
                        $ops[] = [$page, self::textOp($cols[$c], $col['x'], $h - $item['zeilen'][$r], $col['breite'], $item['groesse'] ?? 10)];
                    }
                }
            }
            return $ops;
        }
        if (isset($item['von'])) {
            $v = mb_substr($v, $item['von'], $item['laenge'] ?? null);
            if ($v === '') {
                return [];
            }
        }
        if (isset($item['abstand'])) {
            // Zeichen einzeln in Kästchen (z. B. Kundennummer)
            $ops = [];
            foreach (mb_str_split($v) as $i => $ch) {
                $ops[] = [$page, self::textOp($ch, $item['x'] + $i * $item['abstand'], $h - $item['y'], 20, $item['groesse'] ?? 10)];
            }
            return $ops;
        }
        return [[$page, self::textOp($v, $item['x'], $h - $item['y'], $item['breite'] ?? 300, $item['groesse'] ?? 10)]];
    }

    private static function textOp(string $text, float $x, float $y, float $maxWidth, float $size): string
    {
        return sprintf('%s %.1f %.1f %.1f %.1f FitShow', self::psString($text), $x, $y, $maxWidth, $size);
    }

    /** PostScript-String in ISO-8859-1; Sonderzeichen und alles außerhalb von ASCII als Oktal-Escape */
    private static function psString(string $utf8): string
    {
        $latin1 = @iconv('UTF-8', 'ISO-8859-1//TRANSLIT', str_replace(['€', '„', '“', '–'], ['EUR', '"', '"', '-'], $utf8));
        if ($latin1 === false) {
            $latin1 = preg_replace('/[^\x20-\x7e]/', '?', $utf8);
        }
        $out = '';
        foreach (str_split($latin1) as $ch) {
            $o = ord($ch);
            $out .= ($o < 32 || $o > 126 || $ch === '(' || $ch === ')' || $ch === '\\') ? sprintf('\\%03o', $o) : $ch;
        }
        return "($out)";
    }

    private static function prolog(): string
    {
        return <<<PS
/Helvetica findfont dup length dict begin {1 index /FID ne {def} {pop pop} ifelse} forall
/Encoding ISOLatin1Encoding def currentdict end /HelvLatin1 exch definefont pop
% (text) x y maxbreite groesse FitShow – Schrift verkleinern, bis der Text passt (min. 6 pt)
/FitShow { /fsz exch def /fmw exch def moveto
  { /HelvLatin1 findfont fsz scalefont setfont dup stringwidth pop fmw le fsz 6 le or { exit } if /fsz fsz 0.5 sub def } loop
  0 0 0.55 setrgbcolor show } def

PS;
    }
}
