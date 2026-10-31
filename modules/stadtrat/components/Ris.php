<?php

namespace humhub\modules\stadtrat\components;

use humhub\modules\stadtrat\models\Sitzung;
use humhub\modules\stadtrat\Module;
use humhub\modules\stadtrat\notifications\TagesordnungNeu;
use humhub\modules\user\models\User;
use Yii;
use yii\helpers\Url;

/**
 * Abgleich mit dem öffentlichen Ratsinformationssystem (Gast-Schnittstelle, nur was dort jeder ohne Anmeldung sieht):
 * Sitzungen der Stadt Uffenheim mit Gremium, Ort, Beginn und Tagesordnung des öffentlichen Teils.
 * Texte werden unverändert übernommen – keine Kürzung, keine Bewertung; nur bei Bauanträgen fehlt die genaue Adresse (ohneAdresse).
 * Abo je Konto (Modul-Einstellung „abo“, wird mit dem Konto gelöscht): Push, sobald eine Tagesordnung erscheint.
 */
class Ris
{
    /** Import- und Systemkonten bekommen keine Push-Nachricht */
    private const OHNE = ['stadt-uffenheim', 'mitteilungsblatt', 'gastmeldung', 'facebook'];

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
     * Liste holen, geänderte Sitzungen einzeln nachladen, Push bei neu veröffentlichter Tagesordnung.
     * Beim allerersten Abruf wird nichts gemeldet (sonst käme für jede bekannte Sitzung ein Push).
     * @return string[] Protokollzeilen
     */
    public static function abgleich(bool $dryRun = false): array
    {
        $m = self::module();
        $log = [];
        $ersterAbruf = !Sitzung::find()->exists();
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
            if ($ersterAbruf || !$s->istKommend()) {
                $s->gemeldet_am ??= date('Y-m-d H:i:s'); // nichts nachmelden
            }
            $melden = $punkte && !$hatteTo && $s->gemeldet_am === null;
            $log[] = ($neu ? 'neu ' : 'geändert ') . "{$s->datum} {$s->gremium} ($id): " . count($punkte) . ' TOP' . ($melden ? ', Push' : '');
            if ($dryRun) {
                continue;
            }
            $s->save(false);
            if ($melden) {
                $n = self::melden($s);
                $s->updateAttributes(['gemeldet_am' => date('Y-m-d H:i:s')]);
                $log[] = "  Push an $n Konten";
            }
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

    public static function abo(User $user): bool
    {
        return (bool)self::module()->settings->user($user)->get('abo', false);
    }

    public static function setAbo(User $user, bool $an): void
    {
        self::module()->settings->user($user)->set('abo', $an ? 1 : 0);
    }

    /** Konten mit Abo (und Freigabe) */
    public static function empfaenger(): \humhub\modules\user\components\ActiveQueryUser
    {
        $q = User::find()->active()->andWhere(['not in', 'user.username', self::OHNE])
            ->innerJoin('contentcontainer_setting cs', 'cs.contentcontainer_id = user.contentcontainer_id AND cs.module_id = :m AND cs.name = :n AND cs.value = :v',
                [':m' => 'stadtrat', ':n' => 'abo', ':v' => '1']);
        $liste = self::module()->freigabe;
        return $liste === null ? $q : $q->andWhere(['user.username' => $liste]);
    }

    public static function melden(Sitzung $s): int
    {
        $n = (int)self::empfaenger()->count();
        if ($n) {
            TagesordnungNeu::instance()->payload(self::payload($s))->sendBulk(self::empfaenger());
        }
        Yii::info("Tagesordnung {$s->id} an $n Konten", 'stadtrat');
        return $n;
    }

    public static function payload(Sitzung $s): array
    {
        return ['id' => $s->id, 'gremium' => $s->gremium, 'tag' => $s->tag(), 'top' => count($s->getPunkte())];
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
            . "Gib die Tagesordnung neutral wieder, ohne politische Bewertung.\n"
            . ($kommend ? implode("\n", $kommend) : '- Keine kommenden Sitzungen bekannt.')
            . ($vorbei ? "\nZuletzt:\n" . implode("\n", $vorbei) : '');
    }
}
