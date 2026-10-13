<?php

namespace humhub\modules\facebook\components;

use humhub\modules\facebook\Module;
use Yii;

/** Lesender Zugriff auf die Graph-API von Meta (nur die Seite der Stadt) */
class Api
{
    private ?string $seitenToken = null;

    public function __construct(private Module $module)
    {
    }

    /**
     * Seiten-Token: Ist in secrets/facebook.json ein Systemnutzer- oder Nutzer-Token hinterlegt, liefert
     * die Graph-API damit das Token der Seite; ist es schon ein Seiten-Token, wird es direkt verwendet.
     */
    public function seitenToken(): string
    {
        if ($this->seitenToken !== null) {
            return $this->seitenToken;
        }
        $pageId = $this->module->zugang('page_id') ?? throw new \RuntimeException('page_id fehlt (secrets/facebook.json)');
        $token = $this->module->zugang('token') ?? throw new \RuntimeException('token fehlt (secrets/facebook.json)');
        try {
            $data = $this->get($pageId, ['fields' => 'access_token'], $token);
            $this->seitenToken = $data['access_token'] ?? $token;
        } catch (\RuntimeException $e) {
            // Seiten-Tokens dürfen das Feld access_token nicht abfragen – dann ist es schon das richtige
            $this->seitenToken = $token;
        }
        return $this->seitenToken;
    }

    /** Neueste Beiträge der Seite (ohne Kommentare und Reaktionen) */
    public function beitraege(int $limit = 25): array
    {
        $pageId = $this->module->zugang('page_id');
        $data = $this->get("$pageId/posts", [
            'fields' => 'id,message,created_time,updated_time,permalink_url,full_picture,status_type,'
                . 'attachments{media_type,type,url,unshimmed_url,title,media,subattachments.limit(20){media_type,type,media,url}}',
            'limit' => $limit,
        ], $this->seitenToken());
        return $data['data'] ?? [];
    }

    /** Angaben zur Seite (Name, Link) */
    public function seite(): array
    {
        return $this->get($this->module->zugang('page_id'), ['fields' => 'id,name,link,fan_count'], $this->seitenToken());
    }

    /** Prüft ein Token (Gültigkeit, Ablauf, Rechte) */
    public function tokenInfo(string $token): array
    {
        return $this->get('debug_token', ['input_token' => $token], $token)['data'] ?? [];
    }

    public function get(string $path, array $params, string $token): array
    {
        $url = 'https://graph.facebook.com/' . $this->module->graphVersion . '/' . ltrim($path, '/') . '?' . http_build_query($params);
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_USERAGENT => 'rundumuffni-community',
        ]);
        $body = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        if ($body === false) {
            throw new \RuntimeException("Graph-API nicht erreichbar: $error");
        }
        $json = json_decode((string)$body, true);
        if ($status >= 300 || !is_array($json)) {
            $msg = is_array($json) ? ($json['error']['message'] ?? '') . ' (Code ' . ($json['error']['code'] ?? '?') . ')' : substr((string)$body, 0, 200);
            throw new \RuntimeException("Graph-API HTTP $status: $msg");
        }
        return $json;
    }

    /** Bild herunterladen (Facebook-CDN), höchstens 15 MB */
    public static function laden(string $url): string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_MAXFILESIZE => 15 * 1024 * 1024,
            CURLOPT_USERAGENT => 'rundumuffni-community',
        ]);
        $data = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        if ($data === false || $status >= 300) {
            throw new \RuntimeException("Bild nicht ladbar (HTTP $status)");
        }
        Yii::debug('Bild geladen: ' . strlen($data) . ' Bytes', 'facebook');
        return (string)$data;
    }
}
