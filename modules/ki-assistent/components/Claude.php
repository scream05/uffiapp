<?php

namespace humhub\modules\kiAssistent\components;

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
class Claude
{
    private Client $client;

    public function __construct(string $apiKey, private string $model)
    {
        $this->client = new Client(apiKey: $apiKey);
    }

    /**
     * @param string|array $systemPrompt Text oder Teile (siehe generate)
     * @param array $history [['role' => 'user'|'model', 'text' => '…'], …], letzte Nachricht = Frage
     * @return array [Antworttext, Eingabe-Tokens, Ausgabe-Tokens]
     */
    public function ask(string|array $systemPrompt, array $history): array
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
            'outputConfig' => ['effort' => 'medium'],
        ];
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
