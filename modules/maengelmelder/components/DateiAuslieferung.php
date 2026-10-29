<?php

namespace humhub\modules\maengelmelder\components;

use humhub\modules\file\models\File;
use Yii;

/**
 * Dateien aus Meldungen und WhatsApp sicher ausliefern: Den Typ bestimmt der Inhalt (finfo), nicht die gespeicherte
 * Angabe. Im Browser angezeigt werden nur Rasterbilder und PDF; alles andere (z. B. SVG oder HTML mit Skripten)
 * kommt als Download. Bilder bekommen zusätzlich eine CSP ohne Skripte.
 *
 * Seit HumHub 1.19 liegen Dateien hinter Flysystem: getStore()->get() ist nur noch ein Pfad innerhalb der Ablage,
 * kein Dateipfad mehr. Deshalb hier immer über die Ablage lesen (has/getContent), nie per is_file.
 */
class DateiAuslieferung
{
    public const BILDER = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    public const ANZEIGBAR = [...self::BILDER, 'application/pdf'];

    public static function typ(string $daten): string
    {
        return (string)((new \finfo(FILEINFO_MIME_TYPE))->buffer($daten) ?: 'application/octet-stream');
    }

    /** Ist die gespeicherte Datei vorhanden und ein Rasterbild (nach Inhalt)? */
    public static function istBild(File $file): bool
    {
        $store = $file->getStore();
        try {
            return $store->has() && in_array($store->fs->mimeType($store->get()), self::BILDER, true);
        } catch (\Throwable) {
            return false;
        }
    }

    /** Gespeicherte Datei ausliefern; null, wenn sie in der Ablage fehlt */
    public static function senden(File $file): ?\yii\web\Response
    {
        $store = $file->getStore();
        if (!$store->has()) {
            return null;
        }
        $daten = $store->getContent();
        $typ = self::typ($daten);
        $response = Yii::$app->response;
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        if (in_array($typ, self::BILDER, true)) {
            $response->headers->set('Content-Security-Policy', "default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; sandbox");
        }
        $inline = in_array($typ, self::ANZEIGBAR, true);
        return $response->sendContentAsFile($daten, $file->file_name, ['mimeType' => $inline ? $typ : 'application/octet-stream', 'inline' => $inline]);
    }
}
