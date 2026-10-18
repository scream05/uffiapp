<?php

namespace humhub\modules\notdienst\components;

use Yii;

/**
 * Apotheken-Notdienst von der Schnittstelle der Bayerischen Landesapothekerkammer (XML, ca. 4 Wochen,
 * je Notdienstzeitraum die 4 nächstgelegenen Apotheken nach Straßenweg ab Uffenheim).
 * Abruf per Crontab (notdienst/abrufen), gespeichert in @runtime/notdienst.json; bei Fehlern bleibt der letzte Stand.
 */
class Notdienst
{
    public const QUELLE = 'Der Apotheken-Notdienstplan der örtlichen Apotheken in Bayern wird durch die Bayerische Landesapothekerkammer, '
        . 'Maria-Theresia-Str. 28, 81675 München zur Verfügung gestellt. Bitte beachte das dortige Impressum, '
        . 'den Haftungsausschluss und die Datenschutzhinweise der Bayerischen Landesapothekerkammer.';
    public const QUELLE_LINKS = [
        'Impressum' => 'https://www.blak.de/service/impressum',
        'Haftungsausschluss' => 'https://www.blak.de/service/haftungsausschluss',
        'Datenschutzhinweise' => 'https://www.blak.de/service/datenschutz',
    ];
    private const DAYS = ['So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa'];

    public static function file(): string
    {
        return Yii::getAlias('@runtime/notdienst.json');
    }

    /** Holt die Daten und speichert sie; liefert die Anzahl der Einträge */
    public static function fetch(string $url): int
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_USERAGENT => 'community.rundumuffni.de Notdienst',
        ]);
        $raw = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($raw === false || $status !== 200) {
            throw new \RuntimeException("HTTP $status");
        }
        $xml = @simplexml_load_string($raw);
        if ($xml === false || (string)$xml->code !== 'OK') {
            throw new \RuntimeException('Antwort nicht OK: ' . ($xml === false ? 'kein XML' : (string)$xml->code));
        }
        $entries = [];
        foreach ($xml->entries->entry as $e) {
            $entries[] = [
                'von' => strtotime((string)$e->from),
                'bis' => strtotime((string)$e->to),
                'name' => trim((string)$e->name),
                'strasse' => trim((string)$e->street),
                'plz' => trim((string)$e->zipCode),
                'ort' => trim((string)$e->location),
                'ortsteil' => trim((string)$e->subLocation),
                'telefon' => trim((string)$e->phone),
                'lat' => (float)$e->lat,
                'lon' => (float)$e->lon,
                'km' => round((float)$e->distance / 1000, 1),
            ];
        }
        if ($entries === []) {
            throw new \RuntimeException('keine Einträge');
        }
        $tmp = self::file() . '.tmp';
        file_put_contents($tmp, json_encode(['abgerufen' => time(), 'eintraege' => $entries], JSON_UNESCAPED_UNICODE));
        rename($tmp, self::file());
        return count($entries);
    }

    /** @return array|null ['abgerufen' => Zeitstempel, 'eintraege' => [...]] */
    public static function load(): ?array
    {
        $data = @json_decode((string)@file_get_contents(self::file()), true);
        return is_array($data['eintraege'] ?? null) ? $data : null;
    }

    /**
     * Notdienstzeiträume ab jetzt (der laufende zuerst), je ['von', 'bis', 'apotheken' => [...] nach Entfernung].
     */
    public static function periods(int $count = 3, ?int $now = null): array
    {
        $now ??= time();
        $byPeriod = [];
        foreach (self::load()['eintraege'] ?? [] as $e) {
            if ($e['bis'] > $now) {
                $byPeriod[$e['von'] . '-' . $e['bis']][] = $e;
            }
        }
        ksort($byPeriod);
        $out = [];
        foreach (array_slice($byPeriod, 0, $count) as $list) {
            usort($list, fn($a, $b) => $a['km'] <=> $b['km']);
            $out[] = ['von' => $list[0]['von'], 'bis' => $list[0]['bis'], 'apotheken' => $list];
        }
        return $out;
    }

    /** Daten älter als 2 Tage oder kein laufender Notdienst → Hinweis auf die Suche der Kammer */
    public static function isStale(?int $now = null): bool
    {
        $now ??= time();
        $data = self::load();
        $periods = self::periods(1, $now);
        return $data === null || $data['abgerufen'] < $now - 2 * 86400 || $periods === [] || $periods[0]['von'] > $now;
    }

    /** „Fr 09.10., 8:30 Uhr“ */
    public static function time(int $ts): string
    {
        return self::DAYS[(int)date('w', $ts)] . ' ' . date('d.m.', $ts) . ', ' . date('G:i', $ts) . ' Uhr';
    }

    /** Wie time(), in der Sprache des Nutzers („Fr 09.10., 8:30 Uhr“ bzw. z. B. „Fri 09.10., 08:30“) */
    public static function zeit(int $ts): string
    {
        return str_starts_with(Yii::$app->language, 'de') ? self::time($ts)
            : Yii::$app->formatter->asDatetime($ts, 'EEE dd.MM., HH:mm');
    }

    public static function address(array $e): string
    {
        return $e['strasse'] . ', ' . $e['plz'] . ' ' . $e['ort'] . ($e['ortsteil'] !== '' ? '-' . $e['ortsteil'] : '');
    }

    public static function mapUrl(array $e): string
    {
        return 'https://www.openstreetmap.org/?mlat=' . $e['lat'] . '&mlon=' . $e['lon'] . '#map=17/' . $e['lat'] . '/' . $e['lon'];
    }

    public static function tel(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone);
        return 'tel:' . (str_starts_with($digits, '0') ? '+49' . substr($digits, 1) : $digits);
    }

    /** Text für den KI-Assistenten */
    public static function forAssistant(): string
    {
        $module = Yii::$app->getModule('notdienst');
        $lines = ["## Notrufnummern und Notdienste (Seite in der Community: " . \yii\helpers\Url::to(['/notdienst/index'], true) . ")"];
        foreach ($module->notrufe as [$label, $nr, $hint]) {
            $lines[] = "- $label: $nr" . ($hint !== '' ? " ($hint)" : '');
        }
        $lines[] = "- Zahnärztlicher Notdienst: {$module->zahnarztUrl}";
        $lines[] = '';
        $lines[] = '### Apotheken-Notdienst (nächstgelegene dienstbereite Apotheken ab Uffenheim, Entfernung Straßenweg; '
            . 'Daten der Bayerischen Landesapothekerkammer, Suche: ' . $module->suchUrl . ')';
        if (self::isStale()) {
            $lines[] = '(Aktuelle Daten fehlen – auf die Notdienstsuche der Kammer verweisen.)';
        }
        foreach (self::periods(3) as $p) {
            $lines[] = 'Notdienst ' . self::time($p['von']) . ' bis ' . self::time($p['bis']) . ':';
            foreach ($p['apotheken'] as $a) {
                $lines[] = "- {$a['name']}, " . self::address($a) . ", Tel. {$a['telefon']} ({$a['km']} km)";
            }
        }
        return implode("\n", $lines);
    }
}
