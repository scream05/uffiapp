<?php

namespace humhub\modules\kiAssistent\components;

use Anthropic\Bedrock\Client as BedrockRuntime;
use Anthropic\Bedrock\MantleClient;
use Anthropic\Client;
use Anthropic\Core\Exceptions\APIConnectionException;
use Anthropic\Core\Exceptions\APIStatusException;
use Anthropic\Lib\Streaming\MessageAccumulator;
use Anthropic\Messages\RawContentBlockDeltaEvent;
use Anthropic\Messages\TextDelta;
use Anthropic\Messages\Message;
use Anthropic\Messages\ToolUseBlock;

require_once __DIR__ . '/../vendor/autoload.php';

/**
 * Client für die Claude-API (Anthropic PHP SDK, vendor/ per scripts/run-setup.sh ki-composer).
 * @see https://docs.claude.com/en/api/messages
 */
class Claude implements KiModell
{
    private Client|MantleClient $client;

    /** bedrock-runtime kennt die automatische Cache-Marke (oberstes cacheControl) nicht, nur Marken an Blöcken */
    private bool $runtime = false;

    /**
     * @param int|null $timeout Sekunden für lange Antworten (Standard-Client bricht sonst nach 60 s ohne Daten ab)
     * @param string|null $bedrockRegion AWS-Region: Claude über AWS Bedrock (Mantle) statt direkt bei Anthropic
     * @param array|null $awsIam IAM-Zugangsschlüssel für Bedrock (access_key_id, secret_access_key): AWS-Signatur statt API-Schlüssel
     */
    public function __construct(string $apiKey, private string $model, ?int $timeout = null, private ?string $bedrockRegion = null,
                                ?array $awsIam = null)
    {
        $optionen = null;
        if ($timeout !== null) {
            $http = new \Symfony\Component\HttpClient\Psr18Client(
                \Symfony\Component\HttpClient\HttpClient::create(['timeout' => $timeout, 'max_duration' => $timeout + 300]));
            $optionen = ['transporter' => $http, 'streamingTransporter' => $http];
        }
        if ($bedrockRegion !== null && $awsIam !== null) {
            // IAM-Zugangsschlüssel: bedrock-runtime (InvokeModel, AWS-Signatur) über ein EU-Inferenzprofil
            // (claude-haiku-5-5 → eu.anthropic.claude-haiku-5-5); vollständige IDs (eu./global./anthropic.…) bleiben unverändert
            $this->model = preg_match('/^(eu|global|us|apac|jp)\.|^anthropic\./', $model) ? $model : 'eu.anthropic.' . $model;
            $this->client = BedrockRuntime::withCredentials($awsIam['access_key_id'], $awsIam['secret_access_key'],
                $bedrockRegion, requestOptions: $optionen);
            $this->runtime = true;
            return;
        }
        if ($bedrockRegion !== null) {
            // Bedrock-API-Schlüssel: Mantle (Messages-API); Modellnamen tragen den Vorsatz „anthropic.“
            $this->model = str_starts_with($model, 'anthropic.') ? $model : 'anthropic.' . $model;
            $this->client = new MantleClient(apiKey: $apiKey, awsRegion: $bedrockRegion, requestOptions: $optionen);
            return;
        }
        $this->client = new Client(apiKey: $apiKey, requestOptions: $optionen);
    }

    public function name(): string
    {
        return $this->bedrockRegion !== null ? 'Claude (Bedrock)' : 'Claude';
    }

    /**
     * @param string|array $systemPrompt Text oder Teile (siehe generate)
     * @param array $history [['role' => 'user'|'model', 'text' => '…'], …], letzte Nachricht = Frage
     * @return array [Antworttext, Eingabe-Tokens, Ausgabe-Tokens]
     */
    public function ask(string|array $systemPrompt, array $history): array
    {
        [$message, $in, $out] = $this->generate($systemPrompt, $this->messages($history));
        $text = self::text($message);
        if ($text === '') {
            throw new \RuntimeException('Claude lieferte keine Antwort');
        }
        return [$text, $in, $out];
    }

    /**
     * Ein Schritt mit Werkzeugen (Tool Use).
     * @param string|array $systemPrompt Text oder Teile [fest, …, veränderlich]: alle Teile außer dem letzten liest Claude
     *   aus dem Prompt-Cache (1 Stunde), solange sie sich nicht ändern – das spart Zeit und Kosten
     * @param array $messages Claude-„messages“ (frühere Antworten des Modells unverändert, inkl. Denk-Blöcken)
     * @param array $tools Werkzeuge (name, description, inputSchema)
     * @param callable|null $onText fn(string $stueck): Antwort wird gestreamt, sichtbarer Text stückweise übergeben
     * @return array [Message, Eingabe-Tokens, Ausgabe-Tokens]
     */
    public function generate(string|array $systemPrompt, array $messages, array $tools = [], ?callable $onText = null): array
    {
        $params = [
            'model' => $this->model,
            'maxTokens' => 8000,
            'system' => self::system($systemPrompt),
            'messages' => $messages,
            'tools' => $tools ?: null,
            // Zusätzlich innerhalb einer Werkzeug-Runde: Stadtwissen und bisheriges Gespräch aus dem Cache (5 Minuten)
            'cacheControl' => ['type' => 'ephemeral'],
            // low statt medium (26.10.2026): ki-test gleich gut, etwas weniger Denken
            'outputConfig' => ['effort' => 'low'],
        ];
        if ($this->runtime) {
            unset($params['cacheControl']); // die 1-Stunden-Marke am System-Prompt bleibt
        }
        if (!self::kannEffort($this->model)) {
            unset($params['outputConfig']);
        }
        try {
            if ($onText === null) {
                $message = $this->client->messages->create(...$params);
            } else {
                $acc = MessageAccumulator::forMessages();
                foreach ($this->client->messages->createStream(...$params) as $event) {
                    $acc->accumulate($event);
                    if ($event instanceof RawContentBlockDeltaEvent && $event->delta instanceof TextDelta) {
                        $onText($event->delta->text);
                    }
                }
                $message = $acc->message();
            }
        } catch (APIConnectionException $e) {
            throw new \RuntimeException('Claude nicht erreichbar: ' . $e->getMessage(), 0, $e);
        } catch (APIStatusException $e) {
            throw new \RuntimeException('Claude HTTP ' . $e->getCode() . ': ' . $e->getMessage(), 0, $e);
        }
        if ($message->stopReason === 'refusal') {
            throw new \RuntimeException('Claude hat die Anfrage abgelehnt (' . ($message->stopDetails?->category ?? '?') . ')');
        }
        $u = $message->usage;
        // Eingabe nach Preis gewichtet (Cache schreiben 1,25-fach, für 1 Stunde 2-fach, Cache lesen 0,1-fach), damit die
        // Kostenbremse die tatsächlichen Kosten abbildet und nicht jede Werkzeug-Runde voll zählt
        $stunde = (int)($u->cacheCreation?->ephemeral1hInputTokens ?? 0);
        $in = $u->inputTokens + (int)round(1.25 * ((int)$u->cacheCreationInputTokens - $stunde) + 2 * $stunde
                + 0.1 * (int)$u->cacheReadInputTokens);
        return [$message, $in, $u->outputTokens];
    }

    /** „effort“ kennen erst Claude Opus 4.5 und neuere Modelle (Haiku 4.5, Sonnet 4.5 und älter: HTTP 400) */
    private static function kannEffort(string $model): bool
    {
        return !preg_match('/claude-(haiku-[34]|sonnet-4-5|sonnet-4-|sonnet-4$|opus-4-[01]|3)/', $model)
            || preg_match('/claude-sonnet-4-6/', $model);
    }

    /** System-Prompt als Text-Blöcke, Cache-Marke (1 Stunde) hinter dem letzten festen Teil */
    private static function system(string|array $prompt): string|array
    {
        if (is_string($prompt)) {
            return $prompt;
        }
        $prompt = array_values(array_filter($prompt, fn($t) => $t !== ''));
        return array_map(fn($t, $i) => ['type' => 'text', 'text' => $t]
            + ($i === count($prompt) - 2 ? ['cacheControl' => ['type' => 'ephemeral', 'ttl' => '1h']] : []),
            $prompt, array_keys($prompt));
    }

    /** Verlauf aus dem Browser (role user/model) als Claude-„messages“ */
    public function messages(array $history): array
    {
        return array_map(fn($m) => ['role' => $m['role'] === 'model' ? 'assistant' : 'user', 'content' => $m['text']], $history);
    }

    /** Sichtbarer Text einer Modell-Antwort (ohne Denk-Blöcke) */
    public static function text(Message $message): string
    {
        $text = '';
        foreach ($message->content as $block) {
            if ($block->type === 'text') {
                $text .= $block->text;
            }
        }
        return trim($text);
    }

    /** @return array [[id, name, args], …] */
    public static function toolCalls(Message $message): array
    {
        $calls = [];
        foreach ($message->content as $block) {
            if ($block instanceof ToolUseBlock) {
                $calls[] = [$block->id, $block->name, (array)$block->input];
            }
        }
        return $calls;
    }

    public function antwortText($antwort): string
    {
        return self::text($antwort);
    }

    public function werkzeugAufrufe($antwort): array
    {
        return self::toolCalls($antwort);
    }

    public function antwortNachrichten($antwort): array
    {
        return [['role' => 'assistant', 'content' => $antwort->content]]; // unverändert, inkl. Denk-Blöcken
    }

    public function ergebnisNachrichten(array $ergebnisse): array
    {
        // alle Ergebnisse in einer Nachricht zurück
        return [['role' => 'user', 'content' => array_map(fn($e) => ['type' => 'tool_result', 'toolUseID' => $e[0],
            'content' => $e[1], 'isError' => (bool)$e[2]], $ergebnisse)]];
    }

    public function werkzeug(string $system, array $teile, array $werkzeug, int $maxTokens = 4000): array
    {
        $message = $this->client->messages->create(
            model: $this->model,
            maxTokens: $maxTokens,
            system: $system,
            messages: [['role' => 'user', 'content' => self::teile($teile)]],
            tools: [$werkzeug],
            toolChoice: ['type' => 'tool', 'name' => $werkzeug['name']],
        );
        $u = $message->usage;
        $in = $u->inputTokens + (int)$u->cacheCreationInputTokens + (int)$u->cacheReadInputTokens;
        foreach ($message->content as $block) {
            if ($block instanceof ToolUseBlock && $block->name === $werkzeug['name']) {
                return [json_decode(json_encode($block->input), true) ?: [], $in, $u->outputTokens];
            }
        }
        throw new \RuntimeException('Claude lieferte kein Ergebnis (' . $message->stopReason . ')');
    }

    public function inhalt(string $system, array $teile, int $maxTokens = 8000): array
    {
        $message = $this->client->messages->create(model: $this->model, maxTokens: $maxTokens, system: $system,
            messages: [['role' => 'user', 'content' => self::teile($teile)]]);
        return [self::text($message), $message->usage->inputTokens, $message->usage->outputTokens];
    }

    /** Neutrale Inhalte (text/bild/pdf) als Claude-Inhaltsblöcke */
    private static function teile(array $teile): array
    {
        $out = [];
        foreach ($teile as $t) {
            if (isset($t['text'])) {
                $out[] = ['type' => 'text', 'text' => $t['text']];
            } elseif (isset($t['bild'])) {
                $out[] = ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => $t['bild'][0], 'data' => base64_encode($t['bild'][1])]];
            } elseif (isset($t['pdf'])) {
                $out[] = ['type' => 'document', 'source' => ['type' => 'base64', 'media_type' => 'application/pdf', 'data' => base64_encode($t['pdf'])]];
            }
        }
        return $out;
    }
}
