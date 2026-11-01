<?php

namespace humhub\modules\stadtrat\components;

use humhub\modules\stadtrat\models\Sitzung;
use humhub\modules\stadtrat\Module;
use Yii;
use yii\helpers\Url;

/**
 * Abgleich mit dem öffentlichen Ratsinformationssystem (Gast-Schnittstelle, nur was dort jeder ohne Anmeldung sieht):
 * Sitzungen der Stadt Uffenheim mit Gremium, Ort, Beginn und Tagesordnung des öffentlichen Teils.
 * Texte werden unverändert übernommen – keine Kürzung, keine Bewertung; nur bei Bauanträgen fehlt die genaue Adresse (ohneAdresse).
 * Keine Push-Nachrichten (Abo „Neue Tagesordnung“ am 01.11.2026 auf Wunsch entfernt).
 */
class Ris
{
    public static function module(): Module
    {
        return Yii::$app->getModule('stadtrat');
    }

    private static function get(string $pfad, array $params = []): array
    {
        $m = self::module();
        $ch = curl_init($m->api . $pfad . ($params ? '?' . http_build_query($params) : ''));
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20, CURLOPT_USERAGENT => 'community.rundumuffni.de Stadtrat',
            CURLOPT_HTTPHEADER => ['x-orgkey: ' . $m->orgKey, 'x-uniqueid: ' . parse_url($m->risUrl, PHP_URL_HOST) . parse_url($m->risUrl, PHP_URL_PATH),
                'x-customname: ' . trim(parse_url($m->risUrl, PHP_URL_PATH), '/'), 'Accept: application/json']]);
        $raw = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $d = $status === 200 ? json_decode((string)$raw, true) : null;
        if (!is_array($d)) {
            throw new \RuntimeException("RIS $pfad: HTTP $status");
        }
        return $d;
    }

    private static function uhr(?string $iso): ?string
    {
        return $iso && preg_match('/T(\d\d:\d\d)/', $iso, $m) && $m[1] !== '00:00' ? $m[1] : null;
    }

    private static function text(string $s): string
    {
        return trim(preg_replace("/[ \t]+/u", ' ', str_replace("\r\n", "\n", $s)));
    }

    /**
     * Bauanträge nennen Adresse und Flurnummer privater Grundstücke – davon nur die Gemarkung übernehmen
     * („Bauort: Am Hessbach 15, 97215 Uffenheim (FlNr. 3437/17 Gemarkung Uffenheim) (beschließend)“
     * → „Bauort: Gemarkung Uffenheim (beschließend)“). Das Original steht im RIS.
     */
    public static function ohneAdresse(string $s): string
    {
        return preg_replace_callback('/^(Bauort|Baugrundstück|Grundstück|Standort)\s*:\s*(.*)$/mu', function ($m) {
            $art = preg_match('/\((beschließend|vorberatend|zur Kenntnis)\)\s*$/u', $m[2], $a) ? ' (' . $a[1] . ')' : '';
            $ort = preg_match('/Gemarkung\s+([\p{L}-]+)/u', $m[2], $g) ? 'Gemarkung ' . $g[1] : '–';
            return "{$m[1]}: $ort$art";
        }, $s);
    }

    /**
     * Liste holen, geänderte Sitzungen einzeln nachladen.
     * @return string[] Protokollzeilen
     */
    public static function abgleich(bool $dryRun = false): array
    {
        $m = self::module();
        $log = [];
        $liste = self::get('web/guestmeetings', ['client' => $m->mandant,
            'from' => date('Y-m-d', strtotime("-{$m->tageZurueck} days")), 'until' => date('Y-m-d', strtotime("+{$m->tageVoraus} days"))]);
        $ids = [];
        foreach ($liste['items'] ?? [] as $eintrag) {
            if (($eintrag['clientname'] ?? 'Stadt Uffenheim') !== 'Stadt Uffenheim') {
                continue; // nur der Mandant Stadt Uffenheim
            }
            $id = (int)$eintrag['id'];
            $ids[] = $id;
            $s = Sitzung::findOne($id);
            if ($s !== null && $s->ris_stand === (string)$eintrag['opdatetime'] && $s->abgerufen_am > date('Y-m-d H:i:s', strtotime('-1 day'))) {
                continue; // unverändert (spätestens täglich trotzdem neu, falls sich nur Texte ändern)
            }
            $d = self::get('web/guestmeetings/' . $id);
            usleep(300000);
            $punkte = [];
            foreach ($d['parts'] ?? [] as $teil) {
                if (!empty($teil['protectedpart'])) {
                    continue;
                }
                foreach ($teil['agendaitems'] ?? [] as $p) {
                    if (empty($p['restricted'])) {
                        $punkte[] = [(string)$p['numbering'], self::ohneAdresse(self::text((string)$p['name']))];
                    }
                }
            }
            $hinweise = [];
            foreach (['freetextsbeforemeeting', 'freetextsbeforefirstpublicpart', 'freetextsafterfirstpublicpart', 'freetextsaftermeeting'] as $k) {
                foreach ($d[$k] ?? [] as $t) {
                    $hinweise[] = [self::text((string)($t['caption'] ?? '')), self::text(strip_tags((string)($t['content'] ?? '')))];
                }
            }
            $neu = $s === null;
            $s ??= new Sitzung(['id' => $id]);
            $hatteTo = (bool)$s->getPunkte();
            $s->setAttributes([
                'gremium' => (string)($d['committeename'] ?? $eintrag['committeename']),
                'titel' => (string)($d['name'] ?? $eintrag['name']),
                'datum' => substr((string)($d['meetingdate'] ?? $eintrag['meetingdate']), 0, 10),
                'beginn' => self::uhr($d['publicstarttime'] ?? $eintrag['absolutestarttime'] ?? null),
                'ort' => trim(implode(', ', array_filter([$d['locationname'] ?? $eintrag['loc_name'] ?? '', $d['locationstreet'] ?? '']))) ?: null,
                'nichtoeffentlich' => !empty($d['nonepublicpart']) ? 1 : 0,
                'tagesordnung' => $punkte ? json_encode($punkte, JSON_UNESCAPED_UNICODE) : null,
                'hinweise' => $hinweise ? json_encode($hinweise, JSON_UNESCAPED_UNICODE) : null,
                'ris_stand' => (string)$eintrag['opdatetime'],
                'abgerufen_am' => date('Y-m-d H:i:s'),
            ], false);
            $log[] = ($neu ? 'neu ' : 'geändert ') . "{$s->datum} {$s->gremium} ($id): " . count($punkte) . ' TOP';
            if ($dryRun) {
                continue;
            }
            $s->save(false);
        }
        // Im RIS gelöschte (abgesagte) künftige Sitzungen entfernen; vergangene bleiben, bis sie aus dem Zeitraum fallen
        if ($ids && !$dryRun) {
            $weg = Sitzung::deleteAll(['and', ['not in', 'id', $ids], ['>=', 'datum', date('Y-m-d')]]);
            $alt = Sitzung::deleteAll(['<', 'datum', date('Y-m-d', strtotime("-{$m->tageZurueck} days"))]);
            if ($weg + $alt) {
                $log[] = "entfernt: $weg abgesagt, $alt zu alt";
            }
        }
        if (!$dryRun && $log) {
            Yii::$app->cache->delete('ki-assistent-context-aktuell');
        }
        return $log;
    }

    public static function url(?Sitzung $s = null, bool $absolut = false): string
    {
        $space = \humhub\modules\space\models\Space::findOne(['url' => self::module()->spaceUrl]);
        $anker = $s ? ['#' => 'sitzung-' . $s->id] : [];
        return $space ? $space->createUrl('/stadtrat/index', $anker, $absolut) : Url::to(['/stadtrat/index'] + $anker, $absolut);
    }

    /** Wochenüberblick: Sitzungen im Zeitraum [[Y-m-d, uhr, gremium, Anzahl TOP, url], …] */
    public static function zeitraum(string $von, string $bis): array
    {
        $out = [];
        foreach (Sitzung::find()->where(['between', 'datum', $von, $bis])->orderBy(['datum' => SORT_ASC, 'beginn' => SORT_ASC])->all() as $s) {
            $out[] = [$s->datum, (string)$s->beginn, $s->gremium, count($s->getPunkte()), self::url($s, true)];
        }
        return $out;
    }

    /** Für Uffi: kommende Sitzungen mit Tagesordnung, die letzten drei als Rückblick */
    public static function forAssistant(): string
    {
        $zeile = function (Sitzung $s, bool $mitTo) {
            $txt = '- ' . $s->tag() . ($s->beginn ? ", {$s->beginn} Uhr" : '') . ": {$s->titel}" . ($s->ort ? " ({$s->ort})" : '') . ' – ' . self::url($s, true);
            foreach ($s->getHinweise() as [$k, $t]) {
                $txt .= "\n  Hinweis: " . ($k !== '' ? "$k: " : '') . $t;
            }
            if ($mitTo) {
                $punkte = $s->getPunkte();
                $txt .= $punkte ? '' : "\n  Tagesordnung noch nicht veröffentlicht.";
                foreach ($punkte as [$nr, $t]) {
                    $txt .= "\n  TOP $nr: " . mb_substr(preg_replace('/\s+/u', ' ', $t), 0, 300);
                }
                $txt .= $s->nichtoeffentlich ? "\n  Danach nichtöffentlicher Teil." : '';
            }
            return $txt;
        };
        $kommend = array_map(fn($s) => $zeile($s, true), Sitzung::kommende()->limit(6)->all());
        $vorbei = array_map(fn($s) => $zeile($s, false), Sitzung::vergangene()->limit(3)->all());
        return "## Stadtrat und Ausschüsse der Stadt Uffenheim (Sitzungstermine und öffentliche Tagesordnungen aus dem Ratsinformationssystem "
            . self::module()->risUrl . "; Seite in der Community: " . self::url(null, true) . ")\n"
            . "Sitzungen sind öffentlich, Zuhören ist ohne Anmeldung möglich. Beschlüsse und Sitzungsunterlagen stehen nicht im öffentlichen RIS; "
            . "die Niederschriften öffentlicher Sitzungen dürfen Gemeindebürger im Rathaus einsehen (Art. 54 Abs. 3 GO). "
            . "Gib Tagesordnung und Kurzfassungen neutral wieder, ohne politische Bewertung.\n"
            . ($kommend ? implode("\n", $kommend) : '- Keine kommenden Sitzungen bekannt.')
            . ($vorbei ? "\nZuletzt:\n" . implode("\n", $vorbei) : '')
            . (($kurz = Erklaert::forAssistant()) !== '' ? "\n\n" . $kurz : '');
    }
}
