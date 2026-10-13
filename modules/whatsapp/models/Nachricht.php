<?php

namespace humhub\modules\whatsapp\models;

use yii\db\ActiveRecord;

/**
 * Eingegangene WhatsApp-Nachricht (Text, Bild oder PDF).
 *
 * @property int $id
 * @property string $wamid
 * @property string|null $wa_id
 * @property string|null $absender_name
 * @property string $typ
 * @property string|null $text
 * @property string|null $media_id
 * @property string|null $mime
 * @property string|null $datei
 * @property int $weitergeleitet
 * @property string $empfangen_at
 * @property string $status
 * @property string|null $fehler
 * @property string|null $ergebnis
 * @property string|null $gruppe
 */
class Nachricht extends ActiveRecord
{
    public const STATUS_NEU = 'neu';
    public const STATUS_VERARBEITET = 'verarbeitet';
    public const STATUS_IGNORIERT = 'ignoriert';
    public const STATUS_FEHLER = 'fehler';

    /** Nachrichtentypen, die ausgewertet werden */
    public const TYPEN = ['text', 'image', 'document'];

    public static function tableName()
    {
        return 'wa_nachricht';
    }

    /** Verzeichnis für heruntergeladene Bilder/PDFs (dauerhaftes Datenvolume, außerhalb des Webroots) */
    public static function dir(): string
    {
        $dir = '/data/whatsapp';
        if (!is_dir($dir)) {
            mkdir($dir, 0770, true);
        }
        return $dir;
    }

    public function getPfad(): ?string
    {
        return $this->datei ? self::dir() . '/' . $this->datei : null;
    }

    public function isBild(): bool
    {
        return $this->datei !== null && str_starts_with((string)$this->mime, 'image/');
    }

    public function afterDelete()
    {
        parent::afterDelete();
        if ($this->getPfad() && is_file($this->getPfad())) {
            @unlink($this->getPfad());
        }
    }
}
