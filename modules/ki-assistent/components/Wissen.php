<?php

namespace humhub\modules\kiAssistent\components;

/**
 * Stadtwissen aus wissen/ (Markdown von uffenheim.de, siehe scripts/stadtwissen.py), im Container unter /srv/wissen.
 * Gesucht wird in Abschnitten (wissen/.abschnitte.json: Seiten an „## “ zerlegt, bei Satzungen je Paragraf), damit auch
 * Stellen tief in langen Seiten gefunden werden und ungekürzt bei der KI ankommen. Seiten mit „immer: ja“ gehen bei jeder
 * Frage mit, von den übrigen die zur Frage passendsten Abschnitte (Suche nach Bedeutung, ersatzweise Stichwortsuche BM25),
 * dazu Links auf weitere Treffer.
 */
class Wissen
{
    private const DIR = '/srv/wissen';
    /** Höchstens so viele Zeichen an passenden Abschnitten pro Frage */
    private const BUDGET = 36000;
    private const MAX_CHUNKS = 14;
    /** Seiten bis zu dieser Länge kommen ganz mit, wenn ein Abschnitt passt (z. B. Leistung: Unterlagen + Kosten) */
    private const WHOLE_PAGE = 5000;
    /** Kombinierte Suche: so viele der besten Abschnitte je Verfahren zählen, Glättung der Rangfusion */
    private const HYBRID_DEPTH = 40;
    private const RRF_K = 20;
    private const STOPWORDS = ['der', 'die', 'das', 'und', 'oder', 'ist', 'sind', 'ein', 'eine', 'einen', 'dem', 'den', 'des',
        'wie', 'was', 'wer', 'wo', 'wann', 'warum', 'welche', 'welcher', 'welches', 'gibt', 'es', 'ich', 'mir', 'mich', 'sie',
        'ihr', 'wir', 'uns', 'man', 'kann', 'können', 'im', 'in', 'am', 'an', 'auf', 'zu', 'zum', 'zur', 'von', 'vom', 'mit',
        'für', 'bei', 'über', 'nicht', 'noch', 'auch', 'hat', 'haben', 'wird', 'werden', 'bitte', 'mal', 'denn', 'etwas',
        'seit', 'hallo', 'danke', 'gerne', 'sagen', 'wissen', 'erzähl', 'erzähle', 'mehr', 'dazu', 'du', 'dich'];

    /**
     * Alle Abschnitte, je ['rel', 'titel', 'quelle', 'immer', 'nr', 'abschnitt', 'text'], Schlüssel = Position im Index
     * (so liefert sie auch die Suche nach Bedeutung im Embedding-Dienst). Die Vektoren braucht PHP nicht: ohne sie je
     * Stand des Index zwischengespeichert statt bei jeder Frage 19 MB JSON zu lesen.
     * Fehlt der Index (Abgleich noch nicht gelaufen), gilt jede Seite als ein Abschnitt.
     */
    public static function chunks(): array
    {
        static $memo = [null, null];
        $stand = self::stand();
        if ($stand === false || $memo[0] !== $stand) {
            $abschnitte = $stand === false ? null : \Yii::$app->cache->getOrSet(['ki-assistent-abschnitte', $stand], function () {
                $index = @json_decode((string)@file_get_contents(self::DIR . '/.abschnitte.json'), true);
                return is_array($index['abschnitte'] ?? null) && $index['abschnitte'] !== []
                    ? array_map(fn($c) => array_diff_key($c, ['vec' => 0, 'hash' => 0]), $index['abschnitte']) : null;
            }, 2 * 86400);
            $memo = [$stand, $abschnitte ?? array_map(fn($p) => $p + ['nr' => 0, 'abschnitt' => ''], self::pages())];
        }
        return $memo[1];
    }

    /** Änderungszeit von .abschnitte.json (= Stand des Index, ändert sich mit dem nächtlichen Abgleich) oder false */
    private static function stand(): int|false
    {
        $file = self::DIR . '/.abschnitte.json';
        clearstatcache(true, $file);
        return @filemtime($file);
    }

    /**
     * Inhalte aus Spaces der Community (Feld „space“, gesetzt von scripts/stadtwissen.py) nur, wenn der Nutzer sie lesen darf:
     * öffentlich sichtbare Inhalte für alle Angemeldeten, für Gäste nur, wenn auch der Space für Gäste sichtbar ist
     * („gaeste“; ältere Indexe ohne das Feld zeigen Gästen nichts), sonst nur für Mitglieder des Space.
     */
    private static function visible(array $chunks): array
    {
        $member = null;
        $gast = !\Yii::$app->has('user') || \Yii::$app->user->isGuest;
        // Schlüssel bleiben erhalten (Position im Index), passend zum Stichwort-Index
        return array_filter($chunks, function ($c) use (&$member, $gast) {
            if (empty($c['space'])) {
                return true;
            }
            if (!empty($c['oeffentlich'])) {
                return !$gast || !empty($c['gaeste']);
            }
            if ($member === null) {
                $member = [];
                if (!$gast) {
                    $member = array_flip(array_map('intval', \humhub\modules\space\models\Membership::find()
                        ->select('space_id')->where(['user_id' => \Yii::$app->user->id, 'status' => 3])->column()));
                }
            }
            return isset($member[(int)$c['space']]);
        });
    }

    /** @return array[] je Seite ['rel', 'titel', 'quelle', 'immer', 'text'] direkt aus den Markdown-Dateien */
    public static function pages(): array
    {
        $pages = [];
        if (!is_dir(self::DIR)) {
            return $pages;
        }
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(self::DIR, \FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            if ($file->getExtension() !== 'md' || str_contains($file->getPathname(), '/.git/') || $file->getFilename() === 'README.md') {
                continue;
            }
            $raw = file_get_contents($file->getPathname());
            if (!preg_match('/^---\n(.*?)\n---\n(.*)$/s', $raw, $m)) {
                continue;
            }
            $meta = [];
            foreach (explode("\n", $m[1]) as $line) {
                [$k, $v] = array_pad(explode(':', $line, 2), 2, '');
                $meta[trim($k)] = trim($v);
            }
            $pages[] = ['rel' => substr($file->getPathname(), strlen(self::DIR) + 1),
                'titel' => $meta['titel'] ?? $file->getFilename(), 'quelle' => $meta['quelle'] ?? '',
                'immer' => ($meta['immer'] ?? '') === 'ja', 'text' => preg_replace('/^# .*\n+/', '', trim($m[2])),
                'space' => isset($meta['space']) ? (int)$meta['space'] : null, 'oeffentlich' => ($meta['oeffentlich'] ?? '') === 'ja',
                'gaeste' => ($meta['gaeste'] ?? '') === 'ja'];
        }
        usort($pages, fn($a, $b) => strnatcmp($a['quelle'], $b['quelle']));
        return $pages;
    }

    /**
     * Wissensteil des Prompts für die Frage (bei Rückfragen auch die vorherige Frage berücksichtigen).
     * Mit $suche (Frage → [Stand des Index, Abschnitt-Positionen nach Ähnlichkeit], Embeddings::suche) Suche nach
     * Bedeutung und Stichwortsuche zusammen, sonst/ersatzweise nur Stichwortsuche.
     * @param bool $immer Seiten mit „immer: ja“ mitschicken (false, wenn sie über immer() schon im Prompt stehen)
     */
    public static function forQuestion(string $question, ?callable $suche = null, bool $immer = true): string
    {
        $chunks = self::visible(self::chunks());
        if ($chunks === []) {
            return '';
        }
        $ranked = null;
        if ($suche !== null && trim($question) !== '') {
            try {
                [$stand, $rang] = $suche($question);
                if ($stand !== self::stand()) {
                    throw new \RuntimeException("Embedding-Dienst hat einen anderen Stand des Index ($stand)");
                }
                $ranked = self::rankHybrid($chunks, $question, $rang);
            } catch (\Throwable $e) {
                \Yii::warning('KI-Assistent: Suche nach Bedeutung nicht möglich, Stichwortsuche: ' . $e->getMessage(), 'ki-assistent');
            }
        }
        [$selected, $related] = $ranked ?? self::rankByKeywords($chunks, $question);
        return self::build($chunks, $selected, $related, $immer);
    }

    /** Nur die Seiten mit „immer: ja“ (ändern sich höchstens mit dem nächtlichen Abgleich: fester Teil des Prompts) */
    public static function immer(): string
    {
        $chunks = self::visible(self::chunks());
        $byPage = [];
        foreach ($chunks as $i => $c) {
            if ($c['immer']) {
                $byPage[$c['rel']][] = $i;
            }
        }
        if ($byPage === []) {
            return '';
        }
        return "## Grundinformationen von der Website der Stadt Uffenheim (www.uffenheim.de)\n"
            . "Bei Antworten daraus immer die Quelle der jeweiligen Seite verlinken.\n\n"
            . implode("\n\n", array_map(fn($ids) => self::render($chunks, $ids, true), $byPage));
    }

    /**
     * Suche nach Bedeutung und Stichwortsuche zusammen (Reciprocal Rank Fusion): das lokale Embedding-Modell findet
     * umformulierte Fragen, BM25 genaue Begriffe („Biotonne abmelden“), die das Modell allein schlechter trifft.
     * @param array $rang Abschnitt-Positionen nach Ähnlichkeit zur Frage (Embedding-Dienst), beste zuerst
     * @return array|null [ausgewählte Abschnitt-Indizes, weitere Treffer]
     */
    private static function rankHybrid(array $chunks, string $question, array $rang): ?array
    {
        // nur Abschnitte, die der Nutzer sehen darf
        $meaning = array_values(array_filter(array_map('intval', $rang), fn($i) => isset($chunks[$i])));
        if ($meaning === []) {
            return null;
        }
        [, $keywords] = self::rankByKeywords($chunks, $question);
        $fused = [];
        foreach ([$meaning, $keywords] as $list) {
            foreach (array_slice($list, 0, self::HYBRID_DEPTH) as $rank => $i) {
                $fused[$i] = ($fused[$i] ?? 0) + 1 / (self::RRF_K + $rank + 1);
            }
        }
        arsort($fused);
        $ids = array_keys($fused);
        return [array_slice($ids, 0, self::MAX_CHUNKS), $ids];
    }

    /**
     * Stichwort-Index aller Abschnitte: Wort → [Abschnitt → Anzahl], Länge und Titelwörter je Abschnitt. Einmal je
     * Stand von .abschnitte.json berechnet und zwischengespeichert – sonst zerlegt jede Frage alle Abschnitte neu (~1,4 s).
     */
    private static function stichwortIndex(): array
    {
        static $memo = [null, null];
        $stand = self::stand();
        if ($stand !== false && $memo[0] === $stand) {
            return $memo[1];
        }
        $bauen = function () {
            $woerter = $len = $titel = [];
            foreach (self::chunks() as $i => $c) {
                $counts = array_count_values(self::terms($c['titel'] . ' ' . $c['abschnitt'] . ' ' . $c['text']));
                foreach ($counts as $w => $n) {
                    $woerter[(string)$w][$i] = $n;
                }
                $len[$i] = array_sum($counts);
                $titel[$i] = array_values(array_unique(self::terms($c['titel'] . ' ' . $c['abschnitt'])));
            }
            $sortiert = array_map('strval', array_keys($woerter));
            sort($sortiert, SORT_STRING);
            return ['woerter' => $woerter, 'sortiert' => $sortiert, 'len' => $len, 'titel' => $titel];
        };
        // Ohne Index (Abgleich noch nicht gelaufen) jedes Mal neu, das kommt nur ausnahmsweise vor
        $daten = $stand === false ? $bauen() : \Yii::$app->cache->getOrSet(['ki-assistent-stichworte', 2, $stand], $bauen, 2 * 86400);
        $memo = [$stand, $daten];
        return $daten;
    }

    /**
     * Wörter des Index, die zum Begriff passen (wie $matches in rankByKeywords: das Wort beginnt mit dem Begriff, oder der
     * Begriff beginnt mit einem Wort ab 5 Zeichen) – per binärer Suche in der sortierten Liste statt über alle Wörter.
     */
    private static function passendeWoerter(array $index, string $t): array
    {
        $liste = $index['sortiert'];
        [$lo, $hi] = [0, count($liste)];
        while ($lo < $hi) {
            $mid = ($lo + $hi) >> 1;
            strcmp($liste[$mid], $t) < 0 ? $lo = $mid + 1 : $hi = $mid;
        }
        $out = [];
        for ($k = $lo; $k < count($liste) && str_starts_with($liste[$k], $t); $k++) {
            $out[] = $liste[$k];
        }
        for ($l = 5; $l < strlen($t); $l++) {
            if (isset($index['woerter'][$p = substr($t, 0, $l)])) {
                $out[] = $p;
            }
        }
        return $out;
    }

    /** @return array [ausgewählte Abschnitt-Indizes, weitere Treffer] nach BM25 */
    private static function rankByKeywords(array $chunks, string $question): array
    {
        $terms = array_unique(self::terms($question));
        $index = self::stichwortIndex();
        // Begriff passt auf ein Wort, wenn eins mit dem anderen beginnt („gewerbe“ ↔ „gewerbeanmeld“)
        $matches = fn(string $t, string $w) => str_starts_with($w, $t) || (strlen($w) >= 5 && str_starts_with($t, $w));
        $tf = [];
        $df = array_fill_keys($terms, 0);
        foreach ($terms as $t) {
            $treffer = [];
            foreach (self::passendeWoerter($index, $t) as $w) {
                foreach ($index['woerter'][$w] as $i => $n) {
                    if (isset($chunks[$i])) { // nur Abschnitte, die der Nutzer sehen darf
                        $treffer[$i] = ($treffer[$i] ?? 0) + $n;
                    }
                }
            }
            foreach ($treffer as $i => $n) {
                $tf[$i][$t] = $n;
            }
            $df[$t] = count($treffer);
        }
        ksort($tf); // Reihenfolge wie die Abschnitte (bei gleicher Punktzahl entscheidet die Position)
        $docs = array_intersect_key($index['len'], $chunks);
        // BM25: seltene Begriffe zählen mehr, lange Abschnitte werden nicht bevorzugt; Treffer in Titel/Überschrift extra
        $avgLen = array_sum($docs) / max(1, count($docs));
        $scored = [];
        foreach ($tf as $i => $terme) {
            if ($chunks[$i]['immer']) {
                continue;
            }
            $score = 0;
            foreach ($terme as $t => $n) {
                $idf = log(1 + (count($docs) - $df[$t] + 0.5) / ($df[$t] + 0.5));
                $inTitle = array_filter($index['titel'][$i], fn($w) => $matches($t, (string)$w)) !== [];
                $score += $idf * ($n * 2.2 / ($n + 1.2 * (0.25 + 0.75 * $docs[$i] / $avgLen)) + ($inTitle ? 2 : 0));
            }
            $scored[$i] = $score;
        }
        arsort($scored);
        // Nur deutliche Treffer: mindestens 40 % der Punktzahl des besten Abschnitts
        $best = $scored ? reset($scored) : 0;
        return [array_keys(array_filter($scored, fn($score) => $score >= 0.4 * $best)), array_keys($scored)];
    }

    private static function build(array $chunks, array $selected, array $related, bool $immer = true): string
    {
        $byPage = [];
        foreach ($chunks as $i => $c) {
            $byPage[$c['rel']][] = $i;
        }
        $pageLen = fn($rel) => array_sum(array_map(fn($i) => mb_strlen($chunks[$i]['text']), $byPage[$rel]));

        // Abschnitte auswählen (beste zuerst); kurze Seiten ganz, damit z. B. Unterlagen und Kosten beisammen bleiben
        $budget = self::BUDGET;
        $take = [];
        foreach (array_slice($selected, 0, self::MAX_CHUNKS) as $i) {
            $rel = $chunks[$i]['rel'];
            $add = $pageLen($rel) <= self::WHOLE_PAGE ? $byPage[$rel] : [$i];
            $add = array_filter($add, fn($j) => !isset($take[$rel][$j]));
            $len = array_sum(array_map(fn($j) => mb_strlen($chunks[$j]['text']), $add));
            if ($len > $budget) {
                continue;
            }
            $budget -= $len;
            foreach ($add as $j) {
                $take[$rel][$j] = true;
            }
        }

        $out = [];
        foreach ($byPage as $rel => $ids) {
            if ($immer && $chunks[$ids[0]]['immer']) {
                $out[] = self::render($chunks, $ids, true);
            }
        }
        // Seiten in der Reihenfolge ihres besten Abschnitts, darin die Abschnitte in Seitenreihenfolge
        foreach ($take as $rel => $ids) {
            $ids = array_keys($ids);
            sort($ids);
            $out[] = self::render($chunks, $ids, count($ids) === count($byPage[$rel]));
        }
        // Weitere Treffer nur als Link, damit die KI auf die richtige Seite verweisen kann
        $index = [];
        foreach ($related as $i) {
            $rel = $chunks[$i]['rel'];
            if (!isset($take[$rel]) && !isset($index[$rel]) && count($index) < 10) {
                $index[$rel] = "- {$chunks[$i]['titel']}: {$chunks[$i]['quelle']}";
            }
        }
        return "## Informationen von der Website der Stadt Uffenheim (www.uffenheim.de) und aus dem Vereinsverzeichnis der Community\n"
            . "Bei Antworten daraus immer die Quelle der jeweiligen Seite verlinken. "
            . "Von längeren Seiten sind nur die zur Frage passenden Abschnitte enthalten ([…] = ausgelassen).\n\n"
            . implode("\n\n", $out)
            . ($index ? "\n\n### Weitere möglicherweise passende Seiten der Stadt (Inhalt hier nicht geladen, bei Bedarf darauf verweisen)\n" . implode("\n", $index) : '');
    }

    /** Eine Seite mit den gewählten Abschnitten; Lücken als […] */
    private static function render(array $chunks, array $ids, bool $whole): string
    {
        $first = $chunks[$ids[0]];
        $parts = [];
        $prev = -1;
        foreach ($ids as $i) {
            $c = $chunks[$i];
            if (!$whole && $c['nr'] !== $prev + 1) {
                $parts[] = '[…]';
            }
            // Überschriften zwei Ebenen tiefer, damit sie unter dem Seitentitel bleiben
            $parts[] = preg_replace('/^(#+) /m', '$1## ', $c['text']);
            $prev = $c['nr'];
        }
        if (!$whole && $prev !== max(array_column(array_filter($chunks, fn($c) => $c['rel'] === $first['rel']), 'nr'))) {
            $parts[] = '[…]';
        }
        return "### {$first['titel']}\nQuelle: {$first['quelle']}\n" . implode("\n\n", $parts);
    }

    /** Kleingeschrieben, ohne Stoppwörter, grob auf den Wortstamm gekürzt */
    private static function terms(string $text): array
    {
        $text = mb_strtolower(str_replace(['ä', 'ö', 'ü', 'ß', 'Ä', 'Ö', 'Ü'], ['ae', 'oe', 'ue', 'ss', 'ae', 'oe', 'ue'], $text));
        preg_match_all('/[a-z0-9]{3,}/', $text, $m);
        $stop = array_map(fn($w) => str_replace(['ä', 'ö', 'ü', 'ß'], ['ae', 'oe', 'ue', 'ss'], $w), self::STOPWORDS);
        $out = [];
        foreach ($m[0] as $w) {
            if (!in_array($w, $stop, true)) {
                // Häufige Endungen weg („ersten“/„erste“, „Mauern“/„Mauer“)
                $out[] = strlen($w) > 5 ? preg_replace('/(ungen|ung|en|er|es|e|n|s)$/', '', $w) : $w;
            }
        }
        return $out;
    }
}
