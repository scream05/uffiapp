<?php

namespace humhub\modules\kiAssistent\components;

/**
 * Gemeinsame Schnittstelle der KI-Anbieter (Claude, OpenAI). Welcher Anbieter läuft, steht in KI_ANBIETER (compose.yml),
 * die Instanz liefert Module::ki().
 *
 * Inhalte für werkzeug()/inhalt() sind anbieterneutral: ['text' => '…'], ['bild' => [MIME-Typ, Binärdaten]],
 * ['pdf' => Binärdaten].
 */
interface KiModell
{
    /** Name für Protokolle und Fehlermeldungen („Claude“, „OpenAI“) */
    public function name(): string;

    /**
     * @param string|array $systemPrompt Text oder Teile [fest, …, veränderlich]
     * @param array $history [['role' => 'user'|'model', 'text' => '…'], …], letzte Nachricht = Frage
     * @return array [Antworttext, Eingabe-Tokens, Ausgabe-Tokens]
     */
    public function ask(string|array $systemPrompt, array $history): array;

    /**
     * Ein Schritt im Gespräch mit Werkzeugen.
     * @param array $messages Verlauf im Format des Anbieters (Start: messages(), danach antwortNachrichten()/ergebnisNachrichten())
     * @param array $tools Werkzeuge (name, description, inputSchema)
     * @param callable|null $onText fn(string $stueck): Antwort wird gestreamt
     * @return array [Antwort (anbieterspezifisch), Eingabe-Tokens, Ausgabe-Tokens]
     */
    public function generate(string|array $systemPrompt, array $messages, array $tools = [], ?callable $onText = null): array;

    /** Verlauf aus dem Browser (role user/model) im Format des Anbieters */
    public function messages(array $history): array;

    /** Sichtbarer Text einer Antwort aus generate() */
    public function antwortText($antwort): string;

    /** @return array [[id, name, args], …] Werkzeugaufrufe einer Antwort aus generate() */
    public function werkzeugAufrufe($antwort): array;

    /** Nachrichten, die die Antwort (mit Werkzeugaufrufen) unverändert an den Verlauf anhängen */
    public function antwortNachrichten($antwort): array;

    /** @param array $ergebnisse [[id, JSON-Text, istFehler], …] @return array Nachrichten für den Verlauf */
    public function ergebnisNachrichten(array $ergebnisse): array;

    /**
     * Genau ein Aufruf eines festen Werkzeugs (strukturierte Ausgabe), z. B. Termine aus einem Plakat.
     * @param array $werkzeug name, description, inputSchema
     * @return array [Argumente als Array, Eingabe-Tokens, Ausgabe-Tokens]
     */
    public function werkzeug(string $system, array $teile, array $werkzeug, int $maxTokens = 4000): array;

    /** Freie Textantwort auf neutrale Inhalte (Text, Bilder, PDF) @return array [Text, Eingabe-Tokens, Ausgabe-Tokens] */
    public function inhalt(string $system, array $teile, int $maxTokens = 8000): array;
}
