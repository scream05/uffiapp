<?php

namespace humhub\modules\maengelmelder\boten;

use humhub\modules\maengelmelder\models\Mitarbeiter;
use humhub\modules\whatsapp\components\Api;

/**
 * Bote über die WhatsApp Cloud API (Meta). Adresse ist die Telefonnummer des Mitarbeiters.
 * Fotos müssen bei Meta erst hochgeladen werden; das erledigt foto() intern.
 */
class WhatsAppBote implements Bote
{
    public function text(Mitarbeiter $m, string $text, ?string $bezug = null): ?string
    {
        return Api::sendeText($m->telefon, $text, $bezug);
    }

    public function foto(Mitarbeiter $m, string $data, string $mime, string $name, string $caption = ''): ?string
    {
        return Api::sendeBild($m->telefon, Api::hochladen($data, $mime, $name), $caption);
    }

    public function standort(Mitarbeiter $m, float $lat, float $lng, string $name = '', string $adresse = ''): ?string
    {
        return Api::sendeStandort($m->telefon, $lat, $lng, $name, $adresse);
    }

    public function knoepfe(Mitarbeiter $m, string $text, array $knoepfe, ?string $bezug = null): ?string
    {
        return Api::sendeKnoepfe($m->telefon, $text, $knoepfe, $bezug);
    }

    public function liste(Mitarbeiter $m, string $text, string $knopf, array $zeilen, ?string $bezug = null): ?string
    {
        return Api::sendeListe($m->telefon, $text, $knopf, $zeilen, $bezug);
    }

    public function medium(string $ref): array
    {
        return Api::medium($ref);
    }

    public function gelesen(?string $ref): void
    {
        if ($ref !== null) {
            Api::gelesen($ref);
        }
    }

    public function istTelegram(): bool
    {
        return false;
    }
}
