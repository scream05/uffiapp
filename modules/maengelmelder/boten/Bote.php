<?php

namespace humhub\modules\maengelmelder\boten;

use humhub\modules\maengelmelder\models\Mitarbeiter;

/**
 * Ein „Bote“ stellt dem Bauhof genau einen Weg bereit, einen Mitarbeiter zu erreichen – über WhatsApp oder
 * Telegram. Alle Sende-/Empfangsnuancen (Nummer vs. chat_id, Upload, Fenster) stecken im jeweiligen Backend.
 * Die Rückgabe der Sende-Methoden ist die Nachrichten-ID des Kanals (wamid bzw. Telegram message_id) oder null.
 */
interface Bote
{
    public function text(Mitarbeiter $m, string $text, ?string $bezug = null): ?string;

    public function foto(Mitarbeiter $m, string $data, string $mime, string $name, string $caption = ''): ?string;

    public function standort(Mitarbeiter $m, float $lat, float $lng, string $name = '', string $adresse = ''): ?string;

    /** @param array $knoepfe [id => Beschriftung] */
    public function knoepfe(Mitarbeiter $m, string $text, array $knoepfe, ?string $bezug = null): ?string;

    /** @param array $zeilen [id => [Titel, Beschreibung]] */
    public function liste(Mitarbeiter $m, string $text, string $knopf, array $zeilen, ?string $bezug = null): ?string;

    /** Eingegangene Datei (Foto/Dokument) herunterladen; @return array [Inhalt, MIME-Typ] */
    public function medium(string $ref): array;

    /** Eingegangene Nachricht als gelesen markieren (sofern der Kanal das kann) */
    public function gelesen(?string $ref): void;

    public function istTelegram(): bool;
}
