<?php

namespace humhub\modules\kiAssistent\components;

use Yii;

/**
 * Amtliche Warnungen für den Landkreis Neustadt a.d.Aisch-Bad Windsheim aus der Warn-App NINA des Bundes
 * (BBK: Bevölkerungsschutz/MoWaS, Deutscher Wetterdienst, Hochwasserzentralen, Polizei).
 * Offene Schnittstelle https://warnung.bund.de/api31 (Doku: https://nina.api.bund.dev), keine Anmeldung nötig.
 * Läuft innerhalb des zwischengespeicherten Kontexts (10 Minuten); bei Störung nur ein Hinweis auf die Warn-App.
 */
class Warnungen
{
    /** Amtlicher Regionalschlüssel des Landkreises, auf Kreisebene (mit Nullen aufgefüllt) */
    private const ARS = '095750000000';
    private const API = 'https://warnung.bund.de/api31';
    private const SEVERITY = ['Extreme' => 'extreme Gefahr', 'Severe' => 'hohe Gefahr', 'Moderate' => 'Gefahr', 'Minor' => 'Hinweis'];

    public static function forAssistant(): string
    {
        $head = "## Amtliche Warnungen für den Landkreis Neustadt a.d.Aisch-Bad Windsheim (Warn-App NINA des Bundes, Stand "
            . date('d.m.Y, G:i') . " Uhr; Übersicht: https://warnung.bund.de)\n";
        $list = self::get(self::API . '/dashboard/' . self::ARS . '.json');
        if (!is_array($list)) {
            return $head . '(Warnungen gerade nicht abrufbar – auf die Warn-App NINA bzw. https://warnung.bund.de verweisen.)';
        }
        $lines = [];
        foreach (array_slice($list, 0, 8) as $w) {
            $data = $w['payload']['data'] ?? [];
            if (($data['msgType'] ?? '') === 'Cancel' || ($data['valid'] ?? true) === false) {
                continue;
            }
            $line = '- ' . ($w['i18nTitle']['de'] ?? $data['headline'] ?? 'Warnung')
                . ' (' . (self::SEVERITY[$data['severity'] ?? ''] ?? 'Warnung') . ', Quelle: ' . ($data['provider'] ?? 'NINA')
                . (isset($w['sent']) ? ', ausgegeben ' . date('d.m.Y, G:i', strtotime($w['sent'])) . ' Uhr' : '') . ')';
            $detail = isset($w['id']) ? self::get(self::API . '/warnings/' . rawurlencode($w['id']) . '.json') : null;
            $info = $detail['info'][0] ?? null;
            if ($info !== null) {
                $line .= (!empty($info['expires']) ? "\n  gilt bis " . date('d.m.Y, G:i', strtotime($info['expires'])) . ' Uhr' : '')
                    . "\n  " . self::text($info['description'] ?? '', 600)
                    . (!empty($info['instruction']) ? "\n  Empfehlung: " . self::text($info['instruction'], 400) : '');
            }
            $lines[] = $line;
        }
        return $head . ($lines ? implode("\n", $lines) : '- Derzeit keine Warnungen für den Landkreis.');
    }

    private static function get(string $url): ?array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_USERAGENT => 'community.rundumuffni.de Warnungen',
        ]);
        $raw = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($raw === false || $status !== 200) {
            Yii::warning("NINA $url: HTTP $status", 'ki-assistent');
            return null;
        }
        $data = json_decode($raw, true);
        return is_array($data) ? $data : null;
    }

    private static function text(string $html, int $max): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags(str_ireplace(['<br/>', '<br>', '<br />'], ' ', $html)), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        return mb_strlen($text) > $max ? mb_substr($text, 0, $max) . '…' : $text;
    }
}
