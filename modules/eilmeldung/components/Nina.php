<?php

namespace humhub\modules\eilmeldung\components;

use humhub\modules\eilmeldung\models\Eilmeldung;
use Yii;

/**
 * Amtliche Warnungen aus NINA (https://warnung.bund.de/api31, Doku https://nina.api.bund.dev) für den Landkreis
 * als Eilmeldungen: neue Warnung (msgType Alert) ab der Schwere in Module::$ninaSchwere → Eilmeldung + Push;
 * Aktualisierungen (Update) nur als Eilmeldung ohne Push; verschwindet eine Warnung aus der Liste, endet ihre Eilmeldung.
 * Crontab alle 5 Minuten (eilmeldung/nina).
 */
class Nina
{
    private const API = 'https://warnung.bund.de/api31';

    /** @return string[] Meldungen fürs Log; null-Liste = Abruf gestört (dann nichts beenden) */
    public static function abgleich(bool $dryRun = false): array
    {
        $m = Versand::module();
        $liste = self::get(self::API . '/dashboard/' . $m->ars . '.json');
        if (!is_array($liste)) {
            return ['! NINA nicht abrufbar'];
        }
        $log = [];
        $ids = [];
        foreach ($liste as $w) {
            $id = (string)($w['id'] ?? '');
            $data = $w['payload']['data'] ?? [];
            if ($id === '' || ($data['msgType'] ?? '') === 'Cancel' || ($data['valid'] ?? true) === false) {
                continue;
            }
            $ids[] = $id;
            $anbieter = (string)($data['provider'] ?? '');
            $schwere = (string)($data['severity'] ?? '');
            if (!self::relevant($data) || Eilmeldung::find()->where(['quelle_id' => $id])->exists()) {
                continue;
            }
            $detail = self::get(self::API . '/warnings/' . rawurlencode($id) . '.json');
            $info = $detail['info'][0] ?? [];
            $titel = (string)($w['i18nTitle']['de'] ?? $data['headline'] ?? $info['headline'] ?? 'Amtliche Warnung');
            $e = new Eilmeldung([
                'art' => $anbieter === 'DWD' ? 'unwetter' : 'warnung',
                'titel' => mb_substr($titel, 0, 150),
                'text' => trim(self::text($info['description'] ?? '', 1200)
                    . (!empty($info['instruction']) ? "\n\nEmpfehlung: " . self::text($info['instruction'], 600) : '')
                    . "\n\nQuelle: " . ($anbieter ?: 'NINA') . ' über die Warn-App NINA (warnung.bund.de)'),
                'gueltig_bis' => date('Y-m-d H:i:s', !empty($info['expires']) ? strtotime($info['expires']) : strtotime('+12 hours')),
                'quelle' => 'nina',
                'quelle_id' => mb_substr($id, 0, 150),
                'schwere' => $schwere,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            $push = ($data['msgType'] ?? 'Alert') === 'Alert';
            $log[] = "+ $id ($anbieter, $schwere, " . ($data['msgType'] ?? '?') . "): $titel" . ($push ? ' → Push' : ' (Aktualisierung, kein Push)');
            if ($dryRun) {
                continue;
            }
            if (!$e->save()) {
                $log[] = '! ' . json_encode($e->getErrors(), JSON_UNESCAPED_UNICODE);
                continue;
            }
            if ($push) {
                Versand::senden($e);
            }
        }
        // Nicht mehr gelistet: beendet
        foreach (Eilmeldung::find()->where(['quelle' => 'nina', 'beendet_am' => null])->andWhere(['not in', 'quelle_id', $ids ?: ['-']])->all() as $alt) {
            $log[] = "- {$alt->quelle_id}: nicht mehr gelistet, beendet";
            if (!$dryRun) {
                $alt->updateAttributes(['beendet_am' => date('Y-m-d H:i:s')]);
            }
        }
        if ($log && !$dryRun) {
            Versand::geaendert();
        }
        return $log;
    }

    /** Kommt diese Warnung (payload.data aus der NINA-Liste) als Eilmeldung? Schwere je Anbieter, siehe Module::$ninaSchwere */
    public static function relevant(array $data): bool
    {
        $m = Versand::module();
        if (($data['msgType'] ?? '') === 'Cancel' || ($data['valid'] ?? true) === false) {
            return false;
        }
        return in_array((string)($data['severity'] ?? ''), $m->ninaSchwere[(string)($data['provider'] ?? '')] ?? $m->ninaSchwere['*'], true);
    }

    private static function get(string $url): ?array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 8, CURLOPT_USERAGENT => 'community.rundumuffni.de Eilmeldungen']);
        $raw = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($raw === false || $status !== 200) {
            Yii::warning("NINA $url: HTTP $status", 'eilmeldung');
            return null;
        }
        $data = json_decode($raw, true);
        return is_array($data) ? $data : null;
    }

    private static function text(string $html, int $max): string
    {
        $text = trim(preg_replace('/[ \t]+/u', ' ', html_entity_decode(strip_tags(str_ireplace(['<br/>', '<br>', '<br />'], "\n", $html)), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        return mb_strlen($text) > $max ? mb_substr($text, 0, $max) . '…' : $text;
    }
}
