<?php

namespace humhub\modules\maengelmelder\boten;

use humhub\modules\maengelmelder\components\Telegram;
use humhub\modules\maengelmelder\models\Mitarbeiter;

/**
 * Bote über die Telegram Bot API. Adresse ist die chat_id, die der Mitarbeiter beim ersten Start des Bots
 * (persönlicher Link) hinterlassen hat. Kein 24-Std.-Fenster, keine Vorlagen.
 * Texte kommen im WhatsApp-Stil (*fett*); hier werden sie in Telegram-HTML umgesetzt.
 */
class TelegramBote implements Bote
{
    public function text(Mitarbeiter $m, string $text, ?string $bezug = null): ?string
    {
        return Telegram::sendeText($m->telegram_chat_id, self::html($text), $bezug);
    }

    public function foto(Mitarbeiter $m, string $data, string $mime, string $name, string $caption = ''): ?string
    {
        return Telegram::sendeFoto($m->telegram_chat_id, $data, $mime, self::html($caption), $name);
    }

    public function standort(Mitarbeiter $m, float $lat, float $lng, string $name = '', string $adresse = ''): ?string
    {
        return Telegram::sendeStandort($m->telegram_chat_id, $lat, $lng, $name, $adresse);
    }

    public function knoepfe(Mitarbeiter $m, string $text, array $knoepfe, ?string $bezug = null): ?string
    {
        return Telegram::sendeKnoepfe($m->telegram_chat_id, self::html($text), $knoepfe, $bezug);
    }

    public function liste(Mitarbeiter $m, string $text, string $knopf, array $zeilen, ?string $bezug = null): ?string
    {
        return Telegram::sendeListe($m->telegram_chat_id, self::html($text), $knopf, $zeilen, $bezug);
    }

    public function medium(string $ref): array
    {
        return Telegram::medium($ref);
    }

    public function gelesen(?string $ref): void
    {
        // Telegram bietet Bots keine Lesebestätigung – nichts zu tun.
    }

    public function istTelegram(): bool
    {
        return true;
    }

    /** WhatsApp-Markup (*fett*) → Telegram-HTML; Sonderzeichen werden escaped, URLs erkennt Telegram selbst. */
    private static function html(string $text): string
    {
        $text = htmlspecialchars($text, ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8');
        return preg_replace('/\*([^*\n]+)\*/u', '<b>$1</b>', $text);
    }
}
