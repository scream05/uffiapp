<?php

namespace humhub\modules\kiAssistent\components;

/**
 * Client für die OpenAI-API (Chat Completions, ohne SDK per cURL).
 * @see https://platform.openai.com/docs/api-reference/chat
 *
 * Antworten aus generate() sind Arrays: ['text' => '…', 'calls' => [[id, name, argumente-JSON], …]].
 * Den Prompt-Cache nutzt OpenAI von selbst für gleiche Anfänge ab 1024 Tokens (feste Teile des System-Prompts zuerst).
 */
class OpenAi implements KiModell
{
    /**
     * @param string $reasoning Denkaufwand für Reasoning-Modelle (minimal/low/medium/high), leer = nicht senden
     */
    public function __construct(private string $apiKey, private string $model, private string $reasoning = 'low', private int $timeout = 300,
                                private string $basisUrl = 'https://api.openai.com/v1')
    {
    }

    public function name(): string
    {
        return 'OpenAI';
    }

    public function ask(string|array $systemPrompt, array $history): array
    {
        [$antwort, $in, $out] = $this->generate($systemPrompt, $this->messages($history));
        $text = $this->antwortText($antwort);
        if ($text === '') {
            throw new \RuntimeException('OpenAI lieferte keine Antwort');
        }
        return [$text, $in, $out];
    }

    public function generate(string|array $systemPrompt, array $messages, array $tools = [], ?callable $onText = null): array
    {
        $body = ['messages' => array_merge([['role' => 'system', 'content' => self::system($systemPrompt)]], $messages),
            'max_completion_tokens' => 8000];
        if ($tools) {
            $body['tools'] = array_map([self::class, 'tool'], $tools);
        }
        return $this->senden($body, $onText);
    }

    public function messages(array $history): array
    {
        return array_map(fn($m) => ['role' => $m['role'] === 'model' ? 'assistant' : 'user', 'content' => $m['text']], $history);
    }

    public function antwortText($antwort): string
    {
        return trim($antwort['text']);
    }

    public function werkzeugAufrufe($antwort): array
    {
        return array_map(fn($c) => [$c[0], $c[1], json_decode($c[2] ?: '{}', true) ?: []], $antwort['calls']);
    }

    public function antwortNachrichten($antwort): array
    {
        $m = ['role' => 'assistant', 'content' => $antwort['text'] !== '' ? $antwort['text'] : null];
        if ($antwort['calls']) {
            $m['tool_calls'] = array_map(fn($c) => ['id' => $c[0], 'type' => 'function',
                'function' => ['name' => $c[1], 'arguments' => $c[2] ?: '{}']], $antwort['calls']);
        }
        return [$m];
    }

    public function ergebnisNachrichten(array $ergebnisse): array
    {
        return array_map(fn($e) => ['role' => 'tool', 'tool_call_id' => $e[0], 'content' => $e[1]], $ergebnisse);
    }

    public function werkzeug(string $system, array $teile, array $werkzeug, int $maxTokens = 4000): array
    {
        [$antwort, $in, $out] = $this->senden([
            'messages' => [['role' => 'system', 'content' => $system], ['role' => 'user', 'content' => self::teile($teile)]],
            'max_completion_tokens' => $maxTokens,
            'tools' => [self::tool($werkzeug)],
            'tool_choice' => ['type' => 'function', 'function' => ['name' => $werkzeug['name']]],
        ]);
        foreach ($this->werkzeugAufrufe($antwort) as [, $name, $args]) {
            if ($name === $werkzeug['name']) {
                return [$args, $in, $out];
            }
        }
        throw new \RuntimeException('OpenAI lieferte kein Ergebnis (' . ($antwort['ende'] ?? '?') . ')');
    }

    public function inhalt(string $system, array $teile, int $maxTokens = 8000): array
    {
        [$antwort, $in, $out] = $this->senden([
            'messages' => [['role' => 'system', 'content' => $system], ['role' => 'user', 'content' => self::teile($teile)]],
            'max_completion_tokens' => $maxTokens,
        ]);
        return [trim($antwort['text']), $in, $out];
    }

    /** System-Prompt aus Teilen: feste Teile zuerst, damit OpenAI den Anfang aus dem Cache liest */
    private static function system(string|array $prompt): string
    {
        return is_string($prompt) ? $prompt : implode("\n\n", array_filter($prompt, fn($t) => $t !== ''));
    }

    /** Werkzeug im Claude-Format (name, description, inputSchema) als OpenAI-Funktion */
    private static function tool(array $t): array
    {
        return ['type' => 'function', 'function' => ['name' => $t['name'], 'description' => $t['description'] ?? '',
            'parameters' => self::schema($t['inputSchema'] ?? $t['input_schema'] ?? ['type' => 'object'])]];
    }

    /** Leere „properties“ als JSON-Objekt {} statt [] (sonst lehnt OpenAI das Schema ab) */
    private static function schema(array $s): array
    {
        if (($s['type'] ?? null) === 'object') {
            $s['properties'] = empty($s['properties']) ? new \stdClass() : array_map(fn($p) => is_array($p) ? self::schema($p) : $p, $s['properties']);
        }
        if (isset($s['items']) && is_array($s['items'])) {
            $s['items'] = self::schema($s['items']);
        }
        return $s;
    }

    /** Neutrale Inhalte als OpenAI-Inhaltsteile */
    private static function teile(array $teile): array
    {
        $out = [];
        foreach ($teile as $i => $t) {
            if (isset($t['text'])) {
                $out[] = ['type' => 'text', 'text' => $t['text']];
            } elseif (isset($t['bild'])) {
                [$mime, $daten] = $t['bild'];
                $out[] = ['type' => 'image_url', 'image_url' => ['url' => "data:$mime;base64," . base64_encode($daten), 'detail' => 'high']];
            } elseif (isset($t['pdf'])) {
                $out[] = ['type' => 'file', 'file' => ['filename' => ($t['name'] ?? "anhang-$i") . '.pdf',
                    'file_data' => 'data:application/pdf;base64,' . base64_encode($t['pdf'])]];
            }
        }
        return $out;
    }

    /** @return array [['text', 'calls', 'ende'], Eingabe-Tokens (Cache-Anteil zu 10 %), Ausgabe-Tokens] */
    private function senden(array $body, ?callable $onText = null): array
    {
        $body['model'] = $this->model;
        if ($this->reasoning !== '') {
            $body['reasoning_effort'] = $this->reasoning;
        }
        $stream = $onText !== null;
        if ($stream) {
            $body += ['stream' => true, 'stream_options' => ['include_usage' => true]];
        }
        $text = '';
        $calls = []; // index => [id, name, argumente]
        $ende = null;
        $usage = null;
        $puffer = '';
        $roh = '';
        $ch = curl_init(rtrim($this->basisUrl, '/') . '/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . $this->apiKey],
            CURLOPT_POSTFIELDS => json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE),
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_WRITEFUNCTION => function ($ch, $daten) use ($stream, $onText, &$puffer, &$roh, &$text, &$calls, &$ende, &$usage) {
                if (!$stream || curl_getinfo($ch, CURLINFO_RESPONSE_CODE) !== 200) {
                    $roh .= $daten;
                    return strlen($daten);
                }
                $puffer .= $daten;
                while (($pos = strpos($puffer, "\n")) !== false) {
                    $zeile = trim(substr($puffer, 0, $pos));
                    $puffer = substr($puffer, $pos + 1);
                    if (!str_starts_with($zeile, 'data:') || ($json = trim(substr($zeile, 5))) === '[DONE]') {
                        continue;
                    }
                    $e = json_decode($json, true);
                    if (!is_array($e)) {
                        continue;
                    }
                    $usage = $e['usage'] ?? $usage;
                    foreach ($e['choices'] ?? [] as $c) {
                        $d = $c['delta'] ?? [];
                        if (($d['content'] ?? '') !== '') {
                            $text .= $d['content'];
                            $onText($d['content']);
                        }
                        foreach ($d['tool_calls'] ?? [] as $tc) {
                            $i = $tc['index'] ?? 0;
                            $calls[$i] ??= ['', '', ''];
                            $calls[$i][0] .= $tc['id'] ?? '';
                            $calls[$i][1] .= $tc['function']['name'] ?? '';
                            $calls[$i][2] .= $tc['function']['arguments'] ?? '';
                        }
                        if (!empty($d['refusal'])) {
                            $ende = 'refusal';
                        }
                        $ende = $c['finish_reason'] ?? $ende;
                    }
                }
                return strlen($daten);
            },
        ]);
        $ok = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $fehler = curl_error($ch);
        curl_close($ch);
        if ($ok === false && $code === 0) {
            throw new \RuntimeException('OpenAI nicht erreichbar: ' . $fehler);
        }
        if ($code !== 200) {
            $msg = json_decode($roh, true)['error']['message'] ?? mb_substr($roh, 0, 300);
            throw new \RuntimeException("OpenAI HTTP $code: $msg");
        }
        if (!$stream) {
            $r = json_decode($roh, true);
            $m = $r['choices'][0]['message'] ?? [];
            $text = (string)($m['content'] ?? '');
            $calls = array_map(fn($tc) => [$tc['id'], $tc['function']['name'], $tc['function']['arguments'] ?? '{}'], $m['tool_calls'] ?? []);
            $ende = !empty($m['refusal']) ? 'refusal' : ($r['choices'][0]['finish_reason'] ?? null);
            $usage = $r['usage'] ?? null;
        }
        if ($ende === 'refusal') {
            throw new \RuntimeException('OpenAI hat die Anfrage abgelehnt');
        }
        $cache = (int)($usage['prompt_tokens_details']['cached_tokens'] ?? 0);
        $in = (int)($usage['prompt_tokens'] ?? 0) - $cache + (int)round(0.1 * $cache);
        return [['text' => $text, 'calls' => array_values($calls), 'ende' => $ende], $in, (int)($usage['completion_tokens'] ?? 0)];
    }
}
