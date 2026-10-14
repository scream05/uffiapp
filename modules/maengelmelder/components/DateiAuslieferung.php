<?php

namespace humhub\modules\maengelmelder\components;

use Yii;

/**
 * Dateien aus Meldungen und WhatsApp sicher ausliefern: Den Typ bestimmt der Inhalt (finfo), nicht die gespeicherte
 * Angabe. Im Browser angezeigt werden nur Rasterbilder und PDF; alles andere (z. B. SVG oder HTML mit Skripten)
 * kommt als Download. Bilder bekommen zusätzlich eine CSP ohne Skripte.
 */
class DateiAuslieferung
{
    public const BILDER = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    public const ANZEIGBAR = [...self::BILDER, 'application/pdf'];

    public static function typ(string $pfad): string
    {
        return (string)((new \finfo(FILEINFO_MIME_TYPE))->file($pfad) ?: 'application/octet-stream');
    }

    public static function istBild(string $pfad): bool
    {
        return in_array(self::typ($pfad), self::BILDER, true);
    }

    public static function senden(string $pfad, string $name): \yii\web\Response
    {
        $typ = self::typ($pfad);
        $response = Yii::$app->response;
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        if (in_array($typ, self::BILDER, true)) {
            $response->headers->set('Content-Security-Policy', "default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; sandbox");
        }
        $inline = in_array($typ, self::ANZEIGBAR, true);
        return $response->sendFile($pfad, $name, ['mimeType' => $inline ? $typ : 'application/octet-stream', 'inline' => $inline]);
    }
}
