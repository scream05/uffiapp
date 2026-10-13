<?php

namespace humhub\modules\whatsapp\components;

use humhub\modules\whatsapp\Module;
use Yii;

/**
 * WhatsApp Cloud API (Graph-API von Meta): Signaturprüfung des Webhooks, Medien laden und hochladen,
 * Nachrichten senden (Text, Bild, Standort, Knöpfe, Liste, Vorlage), Vorlagen verwalten.
 * @see https://developers.facebook.com/docs/whatsapp/cloud-api
 */
class Api
{
    private static function module(): Module
    {
        return Yii::$app->getModule('whatsapp');
    }

    /** Webhook-Signatur (X-Hub-Signature-256 = HMAC-SHA256 des Rohinhalts mit dem App-Geheimcode) */
    public static function signaturOk(string $body, ?string $header): bool
    {
        $secret = self::module()->zugang('app_secret');
        if ($secret === null || $header === null || !str_starts_with($header, 'sha256=')) {
            return false;
        }
        return hash_equals(hash_hmac('sha256', $body, $secret), substr($header, 7));
    }

    /** @return array [Inhalt, MIME-Typ] einer Mediendatei */
    public static function medium(string $mediaId): array
    {
        $info = self::get($mediaId);
        if (empty($info['url'])) {
            throw new \RuntimeException('Medium ohne URL');
        }
        // Die Download-URL verlangt ebenfalls den Token
        [$status, $data] = self::http('GET', $info['url']);
        if ($status !== 200 || $data === '') {
            throw new \RuntimeException("Download HTTP $status");
        }
        return [$data, (string)($info['mime_type'] ?? '')];
    }

    /**
     * Textnachricht an eine Nummer. Kostenlos und ohne Vorlage nur innerhalb von 24 Stunden nach der letzten
     * Nachricht dieser Person (Kundenservice-Fenster) – darauf achten die Aufrufer.
     * @return string|null ID der gesendeten Nachricht (wamid)
     */
    public static function sendeText(string $waId, string $text, ?string $antwortAuf = null): ?string
    {
        return self::sende($waId, ['type' => 'text', 'text' => ['body' => mb_substr($text, 0, 4000), 'preview_url' => true]], $antwortAuf);
    }

    /** Bild (vorher hochgeladen, siehe hochladen()) mit Bildunterschrift */
    public static function sendeBild(string $waId, string $mediaId, string $caption = ''): ?string
    {
        return self::sende($waId, ['type' => 'image', 'image' => array_filter(['id' => $mediaId, 'caption' => mb_substr($caption, 0, 1024)])]);
    }

    /** Standort als Karte (öffnet am Handy die Karten-App) */
    public static function sendeStandort(string $waId, float $lat, float $lng, string $name = '', string $adresse = ''): ?string
    {
        return self::sende($waId, ['type' => 'location', 'location' => array_filter([
            'latitude' => $lat, 'longitude' => $lng, 'name' => mb_substr($name, 0, 100), 'address' => mb_substr($adresse, 0, 200)])]);
    }

    /**
     * Text mit bis zu 3 Antwortknöpfen
     * @param array $knoepfe [id => Beschriftung (höchstens 20 Zeichen)]
     */
    public static function sendeKnoepfe(string $waId, string $text, array $knoepfe, ?string $antwortAuf = null): ?string
    {
        $buttons = [];
        foreach (array_slice($knoepfe, 0, 3, true) as $id => $titel) {
            $buttons[] = ['type' => 'reply', 'reply' => ['id' => mb_substr((string)$id, 0, 256), 'title' => mb_substr($titel, 0, 20)]];
        }
        return self::sende($waId, ['type' => 'interactive', 'interactive' => [
            'type' => 'button', 'body' => ['text' => mb_substr($text, 0, 1024)], 'action' => ['buttons' => $buttons]]], $antwortAuf);
    }

    /**
     * Auswahlliste (bis zu 10 Einträge)
     * @param array $zeilen [id => [Titel (24 Zeichen), Beschreibung (72 Zeichen)]]
     */
    public static function sendeListe(string $waId, string $text, string $knopf, array $zeilen, ?string $antwortAuf = null): ?string
    {
        $rows = [];
        foreach (array_slice($zeilen, 0, 10, true) as $id => [$titel, $beschreibung]) {
            $rows[] = array_filter(['id' => mb_substr((string)$id, 0, 200), 'title' => mb_substr($titel, 0, 24),
                'description' => mb_substr((string)$beschreibung, 0, 72)]);
        }
        return self::sende($waId, ['type' => 'interactive', 'interactive' => [
            'type' => 'list', 'body' => ['text' => mb_substr($text, 0, 1024)],
            'action' => ['button' => mb_substr($knopf, 0, 20), 'sections' => [['title' => 'Auswahl', 'rows' => $rows]]]]], $antwortAuf);
    }

    /**
     * Genehmigte Vorlage (für den Erstkontakt außerhalb des 24-Stunden-Fensters)
     * @param string[] $parameter Werte für {{1}}, {{2}} … im Text
     * @param string[] $payloads Rückgabewert je Schnellantwort-Knopf (Index = Knopfnummer)
     */
    public static function sendeVorlage(string $waId, string $name, string $sprache, array $parameter = [], array $payloads = []): ?string
    {
        $components = [];
        if ($parameter) {
            $components[] = ['type' => 'body', 'parameters' => array_map(fn($p) => ['type' => 'text', 'text' => mb_substr((string)$p, 0, 300)], array_values($parameter))];
        }
        foreach (array_values($payloads) as $i => $payload) {
            $components[] = ['type' => 'button', 'sub_type' => 'quick_reply', 'index' => (string)$i,
                'parameters' => [['type' => 'payload', 'payload' => $payload]]];
        }
        return self::sende($waId, ['type' => 'template', 'template' => array_filter([
            'name' => $name, 'language' => ['code' => $sprache], 'components' => $components])]);
    }

    /** Datei für den Versand bei Meta hochladen; @return string Medien-ID */
    public static function hochladen(string $data, string $mime, string $dateiname = 'bild.jpg'): string
    {
        $phoneId = self::module()->zugang('phone_number_id') ?? throw new \RuntimeException('phone_number_id fehlt');
        $url = 'https://graph.facebook.com/' . self::module()->graphVersion . "/$phoneId/media";
        [$status, $body] = self::http('POST', $url, null, [
            'messaging_product' => 'whatsapp',
            'type' => $mime,
            'file' => new \CURLStringFile($data, $dateiname, $mime),
        ]);
        $json = json_decode($body, true);
        if ($status >= 300 || empty($json['id'])) {
            throw new \RuntimeException("Hochladen HTTP $status: " . ($json['error']['message'] ?? substr($body, 0, 200)));
        }
        return (string)$json['id'];
    }

    /** Vorlagen des WhatsApp-Kontos (Name, Sprache, Status) */
    public static function vorlagen(): array
    {
        $waba = self::module()->zugang('waba_id') ?? throw new \RuntimeException('waba_id fehlt');
        return self::get("$waba/message_templates?fields=name,language,status,category,rejected_reason&limit=100")['data'] ?? [];
    }

    /** Vorlage zur Prüfung bei Meta einreichen */
    public static function vorlageAnlegen(array $vorlage): array
    {
        $waba = self::module()->zugang('waba_id') ?? throw new \RuntimeException('waba_id fehlt');
        return self::post("$waba/message_templates", $vorlage);
    }

    private static function sende(string $waId, array $nachricht, ?string $antwortAuf = null): ?string
    {
        $phoneId = self::module()->zugang('phone_number_id') ?? throw new \RuntimeException('phone_number_id fehlt');
        $body = ['messaging_product' => 'whatsapp', 'recipient_type' => 'individual', 'to' => $waId] + $nachricht;
        if ($antwortAuf) {
            $body['context'] = ['message_id' => $antwortAuf];
        }
        return self::post("$phoneId/messages", $body)['messages'][0]['id'] ?? null;
    }

    /** Nachricht als gelesen markieren (blaue Haken beim Absender) */
    public static function gelesen(string $wamid): void
    {
        $phoneId = self::module()->zugang('phone_number_id');
        if ($phoneId) {
            self::post("$phoneId/messages", ['messaging_product' => 'whatsapp', 'status' => 'read', 'message_id' => $wamid]);
        }
    }

    public static function get(string $path): array
    {
        return self::json('GET', $path);
    }

    public static function post(string $path, array $body = []): array
    {
        return self::json('POST', $path, $body);
    }

    private static function json(string $method, string $path, ?array $body = null): array
    {
        $url = 'https://graph.facebook.com/' . self::module()->graphVersion . '/' . ltrim($path, '/');
        [$status, $data] = self::http($method, $url, $body);
        $json = json_decode($data, true);
        if ($status >= 300 || !is_array($json)) {
            $msg = is_array($json) ? ($json['error']['message'] ?? '') . ' (Code ' . ($json['error']['code'] ?? '?') . ')' : substr($data, 0, 200);
            throw new \RuntimeException("Graph-API HTTP $status: $msg");
        }
        return $json;
    }

    private static function http(string $method, string $url, ?array $body = null, ?array $multipart = null): array
    {
        $token = self::module()->zugang('token') ?? throw new \RuntimeException('Zugangs-Token fehlt (secrets/whatsapp.json)');
        $ch = curl_init($url);
        $headers = ['Authorization: Bearer ' . $token];
        if ($body !== null) {
            $headers[] = 'Content-Type: application/json';
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_UNICODE));
        } elseif ($multipart !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $multipart);
        }
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_USERAGENT => 'rundumuffni-community',
            CURLOPT_MAXFILESIZE => 25 * 1024 * 1024,
        ]);
        $data = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        if ($data === false) {
            throw new \RuntimeException("Graph-API nicht erreichbar: $error");
        }
        return [$status, (string)$data];
    }
}
