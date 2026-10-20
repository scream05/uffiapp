<?php

namespace humhub\modules\tanken\components;

use humhub\helpers\Html;
use humhub\modules\tanken\Module;
use Yii;
use yii\helpers\Url;

/**
 * Spritpreise der Markttransparenzstelle für Kraftstoffe (MTS-K) über die Schnittstelle von Tankerkönig.
 * Regeln von Tankerkönig: nur bei Bedarf abrufen (hier: wenn jemand Seite/Kasten/Uffi nutzt und die Daten
 * älter als 10 Minuten sind, sonst stündlich), höchstens 1 Anfrage je Minute, keine ungefragte Filterung,
 * Namensnennung (CC BY 4.0), Schlüssel geheim halten. Ein Abruf (list.php) liefert alle Tankstellen im Umkreis.
 */
class Spritpreise
{
    public const SORTEN = ['e5' => 'Super E5', 'e10' => 'Super E10', 'diesel' => 'Diesel'];
    public const QUELLE = 'https://www.tankerkoenig.de';
    public const MTSK = 'https://www.bundeskartellamt.de/DE/Aufgaben/MarkttransparenzstelleFuerKraftstoffe/markttransparenzstellefuerkraftstoffe_node.html';
    private const API = 'https://creativecommons.tankerkoenig.de/json/list.php';

    private static function modul(): Module
    {
        return Yii::$app->getModule('tanken');
    }

    /**
     * Aktuelle Daten, bei Bedarf neu abgerufen.
     * @return array|null ['abgerufen' => ts, 'stationen' => [...]] oder null (kein Schlüssel / noch nie geklappt / veraltet)
     */
    public static function daten(): ?array
    {
        $m = self::modul();
        if (!$m->aktiv()) {
            return null;
        }
        $daten = json_decode((string)$m->settings->get('daten'), true) ?: null;
        $alt = $daten === null || $daten['abgerufen'] < time() - $m->maxAlterMinuten * 60;
        // Nach einem Fehlschlag frühestens nach 5 Minuten erneut versuchen
        if ($alt && (int)$m->settings->get('fehlversuch') < time() - 300 && Yii::$app->mutex->acquire('tanken-abruf', 0)) {
            try {
                $neu = self::abrufen($m);
                if ($neu !== null) {
                    $daten = $neu;
                    $m->settings->set('daten', json_encode($daten));
                    self::verlaufMerken($daten);
                } else {
                    $m->settings->set('fehlversuch', time());
                }
            } finally {
                Yii::$app->mutex->release('tanken-abruf');
            }
        }
        if ($daten === null || $daten['abgerufen'] < time() - $m->veraltetMinuten * 60) {
            return null;
        }
        return $daten;
    }

    private static function abrufen(Module $m): ?array
    {
        $url = self::API . '?' . http_build_query(['lat' => $m->lat, 'lng' => $m->lng, 'rad' => $m->radius,
                'sort' => 'dist', 'type' => 'all', 'apikey' => $m->getApiKey()]);
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 8, CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_USERAGENT => 'rundumuffni.de (Buergerliste Uffenheim e. V.)']);
        $raw = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $fehler = curl_error($ch);
        curl_close($ch);
        $json = is_string($raw) ? json_decode($raw, true) : null;
        if ($status !== 200 || !is_array($json) || empty($json['ok']) || !isset($json['stations'])) {
            // Meldung ohne Adresse loggen (enthält den Schlüssel)
            Yii::warning('Tankerkönig: HTTP ' . $status . ' ' . $fehler . ' ' . mb_substr((string)($json['message'] ?? $raw), 0, 200), 'tanken');
            return null;
        }
        $stationen = [];
        foreach ($json['stations'] as $s) {
            $stationen[] = [
                'id' => (string)$s['id'],
                'name' => trim((string)$s['name']),
                'marke' => trim((string)($s['brand'] ?? '')),
                'strasse' => self::schreibweise(trim(($s['street'] ?? '') . ' ' . ($s['houseNumber'] ?? ''))),
                'plz' => (string)($s['postCode'] ?? ''),
                'ort' => self::schreibweise((string)($s['place'] ?? '')),
                'lat' => (float)$s['lat'],
                'lng' => (float)$s['lng'],
                'km' => round((float)$s['dist'], 1),
                'offen' => (bool)$s['isOpen'],
                'e5' => is_numeric($s['e5'] ?? null) && $s['e5'] > 0 ? (float)$s['e5'] : null,
                'e10' => is_numeric($s['e10'] ?? null) && $s['e10'] > 0 ? (float)$s['e10'] : null,
                'diesel' => is_numeric($s['diesel'] ?? null) && $s['diesel'] > 0 ? (float)$s['diesel'] : null,
            ];
        }
        return ['abgerufen' => time(), 'stationen' => $stationen];
    }

    /** Tiefstpreise der geöffneten Tankstellen im Kasten-Umkreis (Uffenheim) für den Tagesverlauf merken */
    private static function verlaufMerken(array $daten): void
    {
        $offen = array_filter($daten['stationen'], fn($s) => $s['offen'] && $s['km'] <= self::modul()->kastenRadius);
        $zeile = ['zeit' => date('Y-m-d H:i:s', $daten['abgerufen']), 'offen' => count($offen)];
        foreach (array_keys(self::SORTEN) as $sorte) {
            $preise = array_filter(array_column($offen, $sorte));
            $zeile[$sorte] = $preise ? min($preise) : null;
        }
        Yii::$app->db->createCommand()->insert('tanken_verlauf', $zeile)->execute();
    }

    /** Stationen im Umkreis, sortiert nach Preis der Sorte (geschlossene/ohne Preis ans Ende) oder Entfernung */
    public static function liste(array $daten, float $umkreis, string $sorte, bool $nachPreis): array
    {
        $liste = array_values(array_filter($daten['stationen'], fn($s) => $s['km'] <= $umkreis));
        usort($liste, function ($a, $b) use ($sorte, $nachPreis) {
            if ($nachPreis) {
                $pa = $a['offen'] && $a[$sorte] ? $a[$sorte] : 99;
                $pb = $b['offen'] && $b[$sorte] ? $b[$sorte] : 99;
                return [$pa, $a['km']] <=> [$pb, $b['km']];
            }
            return $a['km'] <=> $b['km'];
        });
        return $liste;
    }

    /** Niedrigster Preis einer Sorte unter den geöffneten Stationen der Liste */
    public static function tiefst(array $liste, string $sorte): ?float
    {
        $p = array_filter(array_map(fn($s) => $s['offen'] ? $s[$sorte] : null, $liste));
        return $p ? min($p) : null;
    }

    /** 1.759 → „1,75⁹“ (wie an der Tankstelle) */
    public static function preis(?float $p, bool $html = true): string
    {
        if (!$p) {
            return '–';
        }
        $s = number_format($p, 3, ',', '');
        return $html ? Html::encode(substr($s, 0, -1)) . '<sup>' . substr($s, -1) . '</sup>' : $s . ' €';
    }

    /** Name mit Marke davor, außer der Name enthält die Marke schon (oder umgekehrt) */
    public static function name(array $s): string
    {
        $marke = $s['marke'];
        $erstes = strtok($marke, ' .-');
        if ($marke === '' || ($erstes !== false && stripos($s['name'], $erstes) !== false)) {
            return $s['name'];
        }
        return stripos($marke, $s['name']) !== false ? $marke : "$marke – {$s['name']}";
    }

    /** Nur GROSS geschriebene Angaben umwandeln („WUERZBURGER STR.“ → „Wuerzburger Str.“), sonst unverändert */
    private static function schreibweise(string $s): string
    {
        $s = trim(preg_replace('/\s+/', ' ', $s));
        return $s === mb_strtoupper($s) ? mb_convert_case(mb_strtolower($s), MB_CASE_TITLE) : $s;
    }

    public static function kartenUrl(array $s): string
    {
        return 'https://www.openstreetmap.org/?mlat=' . $s['lat'] . '&mlon=' . $s['lng'] . '#map=17/' . $s['lat'] . '/' . $s['lng'];
    }

    public static function zeit(int $ts): string
    {
        return date('d.m.Y', $ts) === date('d.m.Y') ? Yii::t('TankenModule.base', '{zeit} Uhr', ['zeit' => date('H:i', $ts)])
            : date('d.m.Y H:i', $ts);
    }

    /**
     * „Wann ist Tanken günstig?“: je Stunde der Mittelwert der beobachteten Tiefstpreise der letzten 14 Tage,
     * als Abstand zum Tagesmittel in Cent. Null, solange zu wenig Daten (weniger als 3 Tage oder 12 Stunden).
     */
    public static function tagesverlauf(string $sorte): ?array
    {
        if (!isset(self::SORTEN[$sorte])) {
            return null;
        }
        $rows = (new \yii\db\Query())->from('tanken_verlauf')
            ->select(['h' => 'HOUR(zeit)', 'p' => "AVG($sorte)", 'tage' => 'COUNT(DISTINCT DATE(zeit))'])
            ->where(['>=', 'zeit', date('Y-m-d H:i:s', strtotime('-14 days'))])->andWhere(['not', [$sorte => null]])
            ->groupBy('h')->indexBy('h')->all();
        $tage = (int)(new \yii\db\Query())->from('tanken_verlauf')->where(['>=', 'zeit', date('Y-m-d H:i:s', strtotime('-14 days'))])
            ->andWhere(['not', [$sorte => null]])->count('DISTINCT DATE(zeit)');
        if ($tage < 3 || count($rows) < 12) {
            return null;
        }
        $mittel = array_sum(array_column($rows, 'p')) / count($rows);
        $out = [];
        for ($h = 0; $h < 24; $h++) {
            $out[$h] = isset($rows[$h]) ? round(((float)$rows[$h]['p'] - $mittel) * 100, 1) : null;
        }
        return ['stunden' => $out, 'tage' => $tage];
    }

    /** Für Uffi: alle Tankstellen im Umkreis mit Preisen (frisch abgerufen, nicht im 10-Minuten-Kontext-Speicher) */
    public static function forAssistant(): string
    {
        $daten = self::daten();
        $m = self::modul();
        $kopf = "## Spritpreise rund um Uffenheim (Seite in der Community: " . Url::to(['/tanken/index'], true) . ")\n"
            . "Daten: Markttransparenzstelle für Kraftstoffe (MTS-K) über Tankerkönig, Lizenz CC BY 4.0. Angaben ohne Gewähr; "
            . "Preise ändern sich oft mehrmals täglich – nenne immer die Uhrzeit „Stand“, nenne nur Preise geöffneter Tankstellen als aktuell, "
            . "bewerte keine Marken. Entfernung = Luftlinie ab Uffenheim Marktplatz.\n";
        if ($daten === null) {
            return $kopf . '(Aktuelle Preise sind gerade nicht abrufbar – auf die Seite oder eine Spritpreis-App verweisen.)';
        }
        $z = [$kopf . 'Stand: ' . date('d.m.Y H:i', $daten['abgerufen']) . ' Uhr'];
        foreach (self::liste($daten, $m->radius, 'e5', false) as $s) {
            $z[] = '- ' . self::name($s) . ', ' . $s['strasse'] . ', ' . $s['plz'] . ' ' . $s['ort'] . " ({$s['km']} km, "
                . ($s['offen'] ? 'geöffnet' : 'geschlossen') . '): '
                . implode(', ', array_map(fn($k, $n) => "$n " . self::preis($s[$k], false), array_keys(self::SORTEN), self::SORTEN));
        }
        return implode("\n", $z);
    }
}
