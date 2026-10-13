<?php

namespace humhub\modules\kiAssistent\components;

use Anthropic\Client;
use Anthropic\Core\Exceptions\APIConnectionException;
use Anthropic\Core\Exceptions\APIStatusException;
use Anthropic\Messages\Message;
use Anthropic\Messages\ToolUseBlock;

require_once __DIR__ . '/../vendor/autoload.php';

/**
 * Client für die Claude-API (Anthropic PHP SDK, vendor/ per scripts/run-setup.sh ki-composer).
 * @see https://docs.claude.com/en/api/messages
 */
class Claude
{
    private Client $client;

    public function __construct(string $apiKey, private string $model)
    {
        $this->client = new Client(apiKey: $apiKey);
    }

    /**
     * @param array $history [['role' => 'user'|'model', 'text' => '…'], …], letzte Nachricht = Frage
     * @return array [Antworttext, Eingabe-Tokens, Ausgabe-Tokens]
     */
    public function ask(string $systemPrompt, array $history): array
    {
        [$message, $in, $out] = $this->generate($systemPrompt, self::messages($history));
        $text = self::text($message);
        if ($text === '') {
            throw new \RuntimeException('Claude lieferte keine Antwort');
        }
        return [$text, $in, $out];
    }

    /**
     * Ein Schritt mit Werkzeugen (Tool Use).
     * @param array $messages Claude-„messages“ (frühere Antworten des Modells unverändert, inkl. Denk-Blöcken)
     * @param array $tools Werkzeuge (name, description, inputSchema)
     * @return array [Message, Eingabe-Tokens, Ausgabe-Tokens]
     */
    public function generate(string $systemPrompt, array $messages, array $tools = []): array
    {
        try {
            $message = $this->client->messages->create(
                model: $this->model,
                maxTokens: 8000,
                system: $systemPrompt,
                messages: $messages,
                tools: $tools ?: null,
                // Stadtwissen und Werkzeuge bleiben innerhalb einer Werkzeug-Runde gleich: Wiederholungen aus dem Cache
                cacheControl: ['type' => 'ephemeral'],
                outputConfig: ['effort' => 'medium'],
            );
        } catch (APIConnectionException $e) {
            throw new \RuntimeException('Claude nicht erreichbar: ' . $e->getMessage(), 0, $e);
        } catch (APIStatusException $e) {
            throw new \RuntimeException('Claude HTTP ' . $e->getCode() . ': ' . $e->getMessage(), 0, $e);
        }
        if ($message->stopReason === 'refusal') {
            throw new \RuntimeException('Claude hat die Anfrage abgelehnt (' . ($message->stopDetails?->category ?? '?') . ')');
        }
        $u = $message->usage;
        // Eingabe inkl. Cache, damit das Monatsprotokoll alle verarbeiteten Tokens zeigt
        $in = $u->inputTokens + (int)$u->cacheCreationInputTokens + (int)$u->cacheReadInputTokens;
        return [$message, $in, $u->outputTokens];
    }

    /** Verlauf aus dem Browser (role user/model) als Claude-„messages“ */
    public static function messages(array $history): array
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
}
