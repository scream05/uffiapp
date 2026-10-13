<?php

namespace humhub\modules\mitteilungsblatt\components;

use humhub\modules\content\models\Content;
use humhub\modules\file\models\File;
use humhub\modules\mitteilungsblatt\models\Ausgabe;
use humhub\modules\mitteilungsblatt\models\Seite;
use humhub\modules\mitteilungsblatt\Module;
use humhub\modules\post\models\Post;
use humhub\modules\space\models\Space;
use humhub\modules\user\models\User;
use Yii;
use yii\helpers\Url;

/**
 * Ausgaben von uffenheim.de holen: PDF und Titelbild in die Ablage, Text je Seite in mb_seite (für die Suche),
 * bei neuen Ausgaben ein Beitrag im Space „Rathaus Uffenheim“ (nur wenn freigegeben, nicht beim ersten Lauf).
 * Ersetzt die Stadt eine Datei (andere Adresse), wird die Ausgabe neu geholt.
 */
class Abruf
{
    /** @var callable(string): void */
    private $log;

    public function __construct(private Module $module, callable $log)
    {
        $this->log = $log;
    }

    /** @return array [neu, aktualisiert, gelöscht] */
    public function holen(bool $probelauf = false): array
    {
        [$liste, $hinweis, $termineUrl] = $this->liste();
        if (!$liste) {
            throw new \RuntimeException('Keine Ausgaben auf ' . $this->module->quelle . ' gefunden (Seite umgebaut?)');
        }
        if ($probelauf) {
            foreach ($liste as $e) {
                ($this->log)("{$e['nr']}/{$e['jahr']} vom {$e['datum']}: {$e['url']}\n");
            }
            ($this->log)("Hinweis: " . ($hinweis ?? '–') . "\n");
            ($this->log)("Terminliste: " . ($termineUrl ?? '–') . "\n");
            if ($termineUrl !== null) {
                foreach ($this->termineAus($this->laden($termineUrl)) as $t) {
                    ($this->log)("  Redaktionsschluss {$t['schluss']} → erscheint {$t['erscheint']}" . ($t['vorverlegt'] ? ' (vorverlegt)' : '') . "\n");
                }
            }
            return [0, 0, 0];
        }
        $this->module->settings->set('hinweis', $hinweis);
        if ($termineUrl !== null) {
            try {
                $this->termineSpeichern($termineUrl);
            } catch (\Throwable $ex) {
                ($this->log)("! Terminliste: {$ex->getMessage()}\n");
            }
        }

        $ersterLauf = !Ausgabe::find()->exists();
        $neu = $aktualisiert = 0;
        // Älteste zuerst, damit die neueste Ausgabe im Stream oben steht
        foreach (array_reverse($liste) as $e) {
            $a = Ausgabe::findOne(['jahr' => $e['jahr'], 'nr' => $e['nr']]);
            if ($a !== null && $a->quelle_url === $e['url'] && is_file($a->pdfPfad)) {
                continue;
            }
            $istNeu = $a === null;
            $a ??= new Ausgabe(['jahr' => $e['jahr'], 'nr' => $e['nr'], 'created_at' => date('Y-m-d H:i:s')]);
            try {
                $this->speichern($a, $e);
            } catch (\Throwable $ex) {
                ($this->log)("! {$e['nr']}/{$e['jahr']}: {$ex->getMessage()}\n");
                continue;
            }
            ($this->log)(($istNeu ? '+ ' : '~ ') . "{$a->titel} vom {$a->datum}, {$a->seiten} Seiten\n");
            if ($istNeu) {
                $neu++;
                if (!$ersterLauf && $this->module->istFreigegeben()) {
                    $this->beitrag($a);
                }
            } else {
                $aktualisiert++;
            }
        }
        return [$neu, $aktualisiert, $this->aufraeumen()];
    }

    /**
     * Ausgaben auf der Seite der Stadt (neueste zuerst) und der Hinweis auf die nächste Ausgabe
     * @return array [[['jahr', 'nr', 'datum', 'url'], …], ?string Hinweis, ?string Adresse der Terminliste]
     */
    public function liste(): array
    {
        $html = $this->laden($this->module->quelle);
        $doc = new \DOMDocument();
        @$doc->loadHTML('<?xml encoding="utf-8"?>' . $html, LIBXML_NOERROR);
        $xp = new \DOMXPath($doc);
        $liste = [];
        foreach ($xp->query('//article[contains(@class, "bulletin--item")]') as $art) {
            $titel = trim($xp->evaluate('string(.//*[contains(@class, "bulletin--item--title")])', $art));
            $datum = trim($xp->evaluate('string(.//*[contains(@class, "meta--date")])', $art));
            $href = trim($xp->evaluate('string(.//a[contains(@href, ".pdf")]/@href)', $art));
            // Titel mal „Mitteilungsblatt 21/2026“, mal „Mitteilungsblatt 2026/17“
            if ($href === '' || !preg_match('/\b(20\d\d)\b/', $titel, $j)
                || !preg_match('/\b(\d{1,2})\b/', str_replace($j[1], '', $titel), $n)) {
                ($this->log)("? Eintrag nicht erkannt: $titel\n");
                continue;
            }
            $liste[] = [
                'jahr' => (int)$j[1],
                'nr' => (int)$n[1],
                'datum' => preg_match('/(\d\d)\.(\d\d)\.(\d{4})/', $datum, $d) ? "$d[3]-$d[2]-$d[1]" : date('Y-m-d'),
                'url' => preg_match('#^https?://#', $href) ? $href : 'https://www.uffenheim.de' . $href,
            ];
        }
        $text = preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5));
        $hinweis = preg_match('/Das nächste Mitteilungsblatt[^!]{10,250}?Uhr!?/u', $text, $h) ? trim($h[0]) : null;
        // Terminliste „Termine_Mitteilungsblatt_2026.pdf“ (Redaktionsschluss und Erscheinungstag)
        $termine = trim($xp->evaluate('string(//a[contains(@href, ".pdf") and contains(translate(@href, "TERMIN", "termin"), "termine")]/@href)'));
        if ($termine !== '' && !preg_match('#^https?://#', $termine)) {
            $termine = 'https://www.uffenheim.de' . $termine;
        }
        return [$liste, $hinweis, $termine !== '' ? $termine : null];
    }

    /** Terminliste holen und speichern, wenn sich die Datei geändert hat; Termine anderer Jahre bleiben */
    private function termineSpeichern(string $url): void
    {
        $pdf = $this->laden($url);
        $hash = sha1($pdf);
        if ($hash === $this->module->settings->get('termine_hash')) {
            return;
        }
        $neu = $this->termineAus($pdf);
        if (!$neu) {
            throw new \RuntimeException('keine Termine erkannt (' . basename($url) . ')');
        }
        $jahre = array_unique(array_map(fn($t) => substr($t['erscheint'], 0, 4), $neu));
        $alt = array_filter(Termine::alle($this->module), fn($t) => !in_array(substr($t['erscheint'], 0, 4), $jahre, true));
        $alle = array_merge(array_values($alt), $neu);
        usort($alle, fn($a, $b) => strcmp($a['erscheint'], $b['erscheint']));
        $this->module->settings->set('termine', json_encode($alle));
        $this->module->settings->set('termine_url', $url);
        $this->module->settings->set('termine_hash', $hash);
        ($this->log)('Terminliste: ' . count($neu) . ' Termine ' . implode(', ', $jahre) . ' (' . basename($url) . ")\n");
    }

    /**
     * Zeilen „Dienstag 20.01.2026 [vorverlegt!] Freitag 30.01.2026“: je Zeile Redaktionsschluss, dann Erscheinungstag.
     * Nur plausible Paare (Erscheinen 3–16 Tage nach Schluss); stimmt etwas nicht, lieber gar nichts.
     * @return array<int, array{schluss: string, erscheint: string, vorverlegt: bool}>
     */
    public function termineAus(string $pdf): array
    {
        if (!str_starts_with($pdf, '%PDF')) {
            return [];
        }
        $text = implode("\n", $this->texteAus($pdf));
        preg_match_all('/(\d\d)\.(\d\d)\.(20\d\d)(\s*vorverlegt)?/u', $text, $m, PREG_SET_ORDER);
        if (count($m) < 2 || count($m) % 2 !== 0) {
            return [];
        }
        $termine = [];
        for ($i = 0; $i < count($m); $i += 2) {
            $schluss = "{$m[$i][3]}-{$m[$i][2]}-{$m[$i][1]}";
            $erscheint = "{$m[$i + 1][3]}-{$m[$i + 1][2]}-{$m[$i + 1][1]}";
            $tage = (strtotime($erscheint) - strtotime($schluss)) / 86400;
            if ($tage < 3 || $tage > 16) {
                return [];
            }
            $termine[] = ['schluss' => $schluss, 'erscheint' => $erscheint, 'vorverlegt' => !empty($m[$i][4])];
        }
        return $termine;
    }

    private function speichern(Ausgabe $a, array $e): void
    {
        $pdf = $this->laden($e['url']);
        if (!str_starts_with($pdf, '%PDF')) {
            throw new \RuntimeException('keine PDF-Datei');
        }
        $texte = $this->texteAus($pdf);
        $this->ablegen($a, $pdf, $texte, $e['url'], $this->erscheinungstag($texte[1] ?? '', $e['jahr']) ?? $e['datum']);
    }

    /**
     * Ältere Ausgabe aus einer Datei übernehmen (ohne Beitrag im Stream). Jahr, Nummer und Datum stehen in der
     * Kopfzeile der Titelseite („Jahrgang 2021 | Freitag, den 9. Juli 2021 | Nr 13“), Dateinamen sind dafür zu uneinheitlich.
     * @return string Logzeile
     */
    public function einlesen(string $datei, string $quelle, bool $probelauf = false): string
    {
        $pdf = (string)@file_get_contents($datei);
        if (!str_starts_with($pdf, '%PDF')) {
            return '! ' . basename($datei) . ': keine PDF-Datei';
        }
        $texte = $this->texteAus($pdf);
        $kopf = $this->kopf($texte, basename($datei));
        if ($kopf === null) {
            return '? ' . basename($datei) . ': Kopfzeile der Titelseite nicht erkannt';
        }
        [$jahr, $nr, $datum] = $kopf;
        if (Ausgabe::find()->where(['jahr' => $jahr])->andWhere(['or', ['nr' => $nr], ['and', ['<', 'nr', $nr], ['>=', 'nr_bis', $nr]]])->exists()) {
            return "= Nr. $nr/$jahr schon im Archiv (" . basename($datei) . ')';
        }
        if ($probelauf) {
            return "+ Nr. $nr/$jahr vom $datum, " . count($texte) . ' Seiten (' . basename($datei) . ')';
        }
        $a = new Ausgabe(['jahr' => $jahr, 'nr' => $nr, 'created_at' => date('Y-m-d H:i:s')]);
        $this->ablegen($a, $pdf, $texte, $quelle, $datum);
        return "+ {$a->titel} vom {$a->datum}, {$a->seiten} Seiten (" . basename($datei) . ')';
    }

    /**
     * [Jahr, Nummer, Datum Y-m-d] aus der Kopfzeile der Titelseite („Jahrgang 2021 | Freitag, den 9. Juli 2021 | Nr 13“,
     * 2017 auch „Jahrgang 17“ oder ohne Jahr beim Datum). Ohne Text auf der Titelseite: Nummer aus der Kopfzeile von
     * Seite 2 („Ausgabe 6/17“), Datum aus dem Dateinamen.
     */
    private function kopf(array $texte, string $dateiname): ?array
    {
        $monate = implode('|', Ausgabe::MONATE);
        if (preg_match('/Jahrgang\s+(\d{2,4})\s*\|[^|]{0,40}?(\d{1,2})\.\s*(' . $monate . ')(?:\s+(\d{4}))?\]?\s*\|\s*Nr\.?\s*(\d{1,2})/u',
            $texte[1] ?? '', $m)) {
            // Der Jahrgang gilt vor dem Jahr im Datum (Nr. 12/2025: „Jahrgang 2025 | Freitag, den 30. Mai 2024“)
            $jahr = (int)$m[1] >= 2000 ? (int)$m[1] : ($m[4] !== '' ? (int)$m[4] : 2000 + (int)$m[1]);
            return [$jahr, (int)$m[5], sprintf('%04d-%02d-%02d', $jahr, array_search($m[3], Ausgabe::MONATE), $m[2])];
        }
        if (preg_match('/Ausgabe\s*(\d{1,2})\/(\d{2})(?!\d)/u', $texte[2] ?? '', $n)
            && preg_match('/(\d{2})[-.](\d{2})[-.](20\d{2})/', $dateiname, $d) && (int)$d[3] === 2000 + (int)$n[2]) {
            return [(int)$d[3], (int)$n[1], "$d[3]-$d[2]-$d[1]"];
        }
        return null;
    }

    /** PDF, Text je Seite und Titelbild ablegen; $a braucht Jahr und Nummer */
    private function ablegen(Ausgabe $a, string $pdf, array $texte, string $quelle, string $datum): void
    {
        if (!is_dir($this->module->ablage)) {
            mkdir($this->module->ablage, 0775, true);
        }
        file_put_contents($a->pdfPfad . '.neu', $pdf);
        rename($a->pdfPfad . '.neu', $a->pdfPfad);

        $a->quelle_url = $quelle;
        $a->groesse = strlen($pdf);
        $a->seiten = count($texte);
        $a->datum = $datum;
        // Doppelausgabe: Kopfzeile „| Nr 1/2“ oder „| Nr 1/2/25“
        if (preg_match('/\|\s*Nr\.?\s*(\d{1,2})\s*\/\s*(\d{1,2})(?!\d)/u', $texte[1] ?? '', $d)
            && (int)$d[1] === $a->nr && (int)$d[2] === $a->nr + 1) {
            $a->nr_bis = (int)$d[2];
        }
        if (!$a->save()) {
            throw new \RuntimeException(json_encode($a->getErrors(), JSON_UNESCAPED_UNICODE));
        }
        Seite::deleteAll(['ausgabe_id' => $a->id]);
        $zeilen = [];
        foreach ($texte as $nr => $text) {
            $zeilen[] = [$a->id, $nr, $text];
        }
        Yii::$app->db->createCommand()->batchInsert(Seite::tableName(), ['ausgabe_id', 'seite', 'text'], $zeilen)->execute();
        $this->gs(['-sDEVICE=jpeg', '-dJPEGQ=80', '-r72', '-dFirstPage=1', '-dLastPage=1', '-dTextAlphaBits=4', '-dGraphicsAlphaBits=4',
            '-o', $a->bildPfad, $a->pdfPfad]);
    }

    private function texteAus(string $pdf): array
    {
        $datei = sys_get_temp_dir() . '/mb-' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($datei, $pdf);
        try {
            return $this->texte($datei);
        } finally {
            @unlink($datei);
        }
    }

    /**
     * Text je Seite (Ghostscript). Im PDF fehlt dem Punkt die Unicode-Zuordnung (kommt als U+FFFD heraus),
     * Spalten stehen nebeneinander: Lücken ab 3 Leerzeichen werden zu Zeilenumbrüchen.
     * @return array<int, string> Seite (ab 1) → Text
     */
    private function texte(string $pdf): array
    {
        $tmp = sys_get_temp_dir() . '/mb-' . bin2hex(random_bytes(4));
        mkdir($tmp);
        try {
            $this->gs(['-sDEVICE=txtwrite', '-o', "$tmp/s-%04d.txt", $pdf]);
            $texte = [];
            foreach (glob("$tmp/s-*.txt") as $datei) {
                $t = mb_scrub(str_replace("\u{FFFD}", '.', (string)file_get_contents($datei)), 'UTF-8');
                $t = preg_replace('/\.{3,}/u', ' … ', $t);
                $t = preg_replace('/ {3,}/u', "\n", $t);
                $t = implode("\n", array_filter(array_map('trim', explode("\n", $t)), 'strlen'));
                $texte[(int)substr(basename($datei, '.txt'), 2)] = $t;
            }
            ksort($texte);
            return $texte;
        } finally {
            array_map('unlink', glob("$tmp/*"));
            rmdir($tmp);
        }
    }

    /** „Freitag, den 9. Oktober 2026“ von der Titelseite */
    private function erscheinungstag(string $titelseite, int $jahr): ?string
    {
        $monate = implode('|', Ausgabe::MONATE);
        if (preg_match("/(\d{1,2})\.\s*($monate)\s+($jahr)/u", $titelseite, $m)) {
            return sprintf('%04d-%02d-%02d', $m[3], array_search($m[2], Ausgabe::MONATE), $m[1]);
        }
        return null;
    }

    private function gs(array $args): void
    {
        $cmd = 'gs -q -dNOPAUSE -dBATCH -dSAFER ' . implode(' ', array_map('escapeshellarg', $args)) . ' 2>&1';
        exec($cmd, $out, $code);
        if ($code !== 0) {
            throw new \RuntimeException('Ghostscript: ' . implode(' ', array_slice($out, 0, 3)));
        }
    }

    /** Beitrag im Space mit Titelbild, Verfasser wie beim Website-Import */
    public function beitrag(Ausgabe $a): void
    {
        $space = Space::findOne(['name' => $this->module->space]);
        $konto = User::findOne(['username' => $this->module->konto]);
        if ($space === null || $konto === null) {
            ($this->log)("! Beitrag: Space {$this->module->space} oder Konto {$this->module->konto} fehlt\n");
            return;
        }
        Yii::$app->user->switchIdentity($konto);
        $post = new Post($space);
        $post->message = "**Das Mitteilungsblatt {$a->titel} ist erschienen**\n\n"
            . "Ausgabe vom {$a->datumText} mit {$a->seiten} Seiten – hier in der App lesen und durchsuchen.\n\n"
            . '[Mitteilungsblatt lesen](' . Url::to(['/mitteilungsblatt/index/lesen', 'id' => $a->id], true) . ')';
        $post->content->visibility = Content::VISIBILITY_PUBLIC; // wie die Website-Nachrichten: auch für Gäste
        if (!$post->save()) {
            ($this->log)("! Beitrag: " . json_encode($post->getErrors(), JSON_UNESCAPED_UNICODE) . "\n");
            return;
        }
        if (is_file($a->bildPfad)) {
            $file = new File(['file_name' => "Mitteilungsblatt-{$a->name}.jpg", 'mime_type' => 'image/jpeg', 'size' => filesize($a->bildPfad)]);
            $file->save();
            $file->setStoredFileContent(file_get_contents($a->bildPfad));
            $post->fileManager->attach($file);
        }
        $a->updateAttributes(['post_id' => $post->id]);
    }

    /** Ältere Ausgaben über $behalten hinaus löschen (Beiträge bleiben, der Reader meldet dann „nicht mehr im Archiv“) */
    private function aufraeumen(): int
    {
        if ($this->module->behalten <= 0) {
            return 0;
        }
        $alt = Ausgabe::find()->orderBy(['jahr' => SORT_DESC, 'nr' => SORT_DESC])->offset($this->module->behalten)->all();
        foreach ($alt as $a) {
            @unlink($a->pdfPfad);
            @unlink($a->bildPfad);
            $a->delete();
            ($this->log)("- {$a->titel}\n");
        }
        return count($alt);
    }

    private function laden(string $url): string
    {
        $ctx = stream_context_create(['http' => [
            'timeout' => 60,
            'header' => "User-Agent: community.rundumuffni.de Mitteilungsblatt\r\n",
        ]]);
        $data = @file_get_contents($url, false, $ctx);
        // Bei Weiterleitungen zählt die letzte Statuszeile
        $zeilen = array_filter($http_response_header ?? [], fn($h) => str_starts_with($h, 'HTTP/'));
        $status = (string)end($zeilen);
        if ($data === false || !preg_match('#\s200\b#', $status)) {
            throw new \RuntimeException("Abruf fehlgeschlagen ($status): $url");
        }
        return $data;
    }
}
