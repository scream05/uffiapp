<?php

namespace humhub\modules\maengelmelder\components;

use humhub\modules\maengelmelder\Module;
use Yii;

/**
 * Telegram Bot API (offiziell, kostenlos): Nachrichten senden (Text, Foto, Standort, Inline-Knöpfe),
 * Dateien laden, Webhook einrichten. Gegenstück zur WhatsApp-Klasse Api, aber ohne 24-Std.-Fenster
 * und ohne Vorlagen – ein Bot darf jeden anschreiben, der ihn einmal gestartet hat.
 * @see https://core.telegram.org/bots/api
 */
class Telegram
{
    private static function module(): Module
    {
        return Yii::$app->getModule('maengelmelder');
    }

    public static function aktiv(): bool
    {
        return self::module()->telegramZugang('token') !== null;
    }

    /** Text mit Telegram-HTML (nur <b>); @return string|null message_id */
    public static function sendeText(string $chatId, string $text, ?string $antwortAuf = null): ?string
    {
        return self::nachricht('sendMessage', $chatId, [
            'text' => mb_substr($text, 0, 4000), 'parse_mode' => 'HTML', 'link_preview_options' => ['is_disabled' => false],
        ], $antwortAuf);
    }

    /** Foto aus Rohdaten (kein Vorab-Upload nötig) */
    public static function sendeFoto(string $chatId, string $data, string $mime, string $caption = '', string $name = 'bild.jpg'): ?string
    {
        $res = self::http('sendPhoto', [
            'chat_id' => $chatId,
            'caption' => mb_substr($caption, 0, 1024),
            'parse_mode' => 'HTML',
            'photo' => new \CURLStringFile($data, $name, $mime),
        ]);
        return isset($res['result']['message_id']) ? (string)$res['result']['message_id'] : null;
    }

    /** Standort; mit Name/Adresse als „Venue“ (Ort mit Bezeichnung), sonst als reiner Punkt */
    public static function sendeStandort(string $chatId, float $lat, float $lng, string $name = '', string $adresse = ''): ?string
    {
        if ($name !== '') {
            return self::nachricht('sendVenue', $chatId, [
                'latitude' => $lat, 'longitude' => $lng,
                'title' => mb_substr($name, 0, 100), 'address' => mb_substr($adresse !== '' ? $adresse : $name, 0, 200)]);
        }
        return self::nachricht('sendVenue', $chatId, ['latitude' => $lat, 'longitude' => $lng,
            'title' => 'Standort', 'address' => sprintf('%.5f, %.5f', $lat, $lng)]);
    }

    /**
     * Text mit Inline-Knöpfen (eine Reihe je Knopf)
     * @param array $knoepfe [callback_data => Beschriftung]
     */
    public static function sendeKnoepfe(string $chatId, string $text, array $knoepfe, ?string $antwortAuf = null): ?string
    {
        $rows = [];
        foreach ($knoepfe as $daten => $titel) {
            $rows[] = [['text' => mb_substr((string)$titel, 0, 64), 'callback_data' => mb_substr((string)$daten, 0, 64)]];
        }
        return self::nachricht('sendMessage', $chatId, [
            'text' => mb_substr($text, 0, 4000), 'parse_mode' => 'HTML',
            'reply_markup' => ['inline_keyboard' => $rows],
        ], $antwortAuf);
    }

    /**
     * Auswahlliste (bei Telegram ebenfalls Inline-Knöpfe, einer je Zeile)
     * @param array $zeilen [callback_data => [Titel, Beschreibung]]
     */
    public static function sendeListe(string $chatId, string $text, string $knopf, array $zeilen, ?string $antwortAuf = null): ?string
    {
        $rows = [];
        foreach ($zeilen as $daten => $eintrag) {
            [$titel, $beschreibung] = is_array($eintrag) ? $eintrag : [$eintrag, ''];
            $label = $beschreibung !== '' ? "$titel – $beschreibung" : $titel;
            $rows[] = [['text' => mb_substr((string)$label, 0, 64), 'callback_data' => mb_substr((string)$daten, 0, 64)]];
        }
        return self::nachricht('sendMessage', $chatId, [
            'text' => mb_substr($text, 0, 4000), 'parse_mode' => 'HTML',
            'reply_markup' => ['inline_keyboard' => $rows],
        ], $antwortAuf);
    }

    /** Knopfdruck quittieren (entfernt die Sanduhr am Knopf) */
    public static function callbackBeantworten(string $callbackId): void
    {
        try {
            self::http('answerCallbackQuery', ['callback_query_id' => $callbackId]);
        } catch (\Throwable $e) {
        }
    }

    /** @return array [Inhalt, MIME-Typ] einer über file_id referenzierten Datei */
    public static function medium(string $fileId): array
    {
        $info = self::http('getFile', ['file_id' => $fileId]);
        $pfad = $info['result']['file_path'] ?? null;
        if (!$pfad) {
            throw new \RuntimeException('Telegram-Datei ohne Pfad');
        }
        $token = self::module()->telegramZugang('token');
        [$status, $data] = self::download("https://api.telegram.org/file/bot$token/$pfad");
        if ($status !== 200 || $data === '') {
            throw new \RuntimeException("Telegram-Download HTTP $status");
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($data) ?: 'application/octet-stream';
        return [$data, $mime];
    }

    /** Befehlsmenü des Bots setzen (erscheint im Telegram-Eingabefeld als „/"-Menü). Nur a-z0-9_ erlaubt. */
    public static function setzeBefehle(): array
    {
        return self::http('setMyCommands', ['commands' => json_encode([
            ['command' => 'auftraege', 'description' => 'Meine offenen Aufträge zeigen'],
            ['command' => 'hilfe', 'description' => 'Hilfe anzeigen'],
        ], JSON_UNESCAPED_UNICODE)]);
    }

    public static function getMe(): array
    {
        return self::http('getMe')['result'] ?? [];
    }

    public static function botName(): ?string
    {
        return self::getMe()['username'] ?? null;
    }

    public static function setzeWebhook(string $url, string $secret): array
    {
        return self::http('setWebhook', [
            'url' => $url,
            'secret_token' => $secret,
            'allowed_updates' => json_encode(['message', 'callback_query']),
            'drop_pending_updates' => 'true',
        ]);
    }

    public static function loescheWebhook(): array
    {
        return self::http('deleteWebhook', ['drop_pending_updates' => 'true']);
    }

    public static function webhookInfo(): array
    {
        return self::http('getWebhookInfo')['result'] ?? [];
    }

    private static function nachricht(string $methode, string $chatId, array $body, ?string $antwortAuf = null): ?string
    {
        $body['chat_id'] = $chatId;
        if (isset($body['reply_markup']) && is_array($body['reply_markup'])) {
            $body['reply_markup'] = json_encode($body['reply_markup'], JSON_UNESCAPED_UNICODE);
        }
        if (isset($body['link_preview_options']) && is_array($body['link_preview_options'])) {
            $body['link_preview_options'] = json_encode($body['link_preview_options']);
        }
        if ($antwortAuf) {
            $body['reply_parameters'] = json_encode(['message_id' => (int)$antwortAuf, 'allow_sending_without_reply' => true]);
        }
        $res = self::http($methode, $body);
        return isset($res['result']['message_id']) ? (string)$res['result']['message_id'] : null;
    }

    /** HTTP-Aufruf der Bot-API (multipart, damit auch \CURLStringFile als Foto geht) */
    private static function http(string $methode, array $body = []): array
    {
        $token = self::module()->telegramZugang('token') ?? throw new \RuntimeException('Telegram-Token fehlt (secrets/telegram.json)');
        $ch = curl_init("https://api.telegram.org/bot$token/$methode");
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_USERAGENT => 'rundumuffni-community',
        ]);
        $data = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $fehler = curl_error($ch);
        curl_close($ch);
        if ($data === false) {
            throw new \RuntimeException("Telegram $methode: $fehler");
        }
        $json = json_decode($data, true);
        if (!is_array($json) || !($json['ok'] ?? false)) {
            $msg = is_array($json) ? ($json['description'] ?? '') . ' (Code ' . ($json['error_code'] ?? '?') . ')' : substr($data, 0, 200);
            throw new \RuntimeException("Telegram $methode HTTP $status: $msg");
        }
        return $json;
    }

    private static function download(string $url): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_MAXFILESIZE => 25 * 1024 * 1024,
            CURLOPT_USERAGENT => 'rundumuffni-community',
        ]);
        $data = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        return [$status, (string)$data];
    }
}
