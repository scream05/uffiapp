<?php

namespace humhub\modules\maengelmelder\models;

use humhub\components\ActiveRecord;
use humhub\modules\file\models\File;
use humhub\modules\user\models\User;

/**
 * Verlauf zwischen Bauhof und Mitarbeitern: WhatsApp-Nachrichten in beide Richtungen sowie interne Vermerke
 * (Zuweisung, Statusänderung). Fotos liegen als HumHub-Datei (object_model = diese Klasse) im Upload-Ordner
 * und werden nur über BauhofController::actionDatei ausgeliefert.
 *
 * @property int $id
 * @property int|null $mitarbeiter_id
 * @property int|null $content_id
 * @property string $richtung
 * @property string $typ
 * @property string|null $text
 * @property int|null $file_id
 * @property string|null $media_id
 * @property string|null $wamid
 * @property string|null $bezug_wamid
 * @property string|null $zustellung
 * @property string|null $fehler
 * @property int|null $user_id
 * @property int $verarbeitet
 * @property string|null $gelesen_at
 * @property int $buerger Nachricht an/von der meldenden Person (nicht Mitarbeiter)
 * @property string $created_at
 * @property-read Mitarbeiter|null $mitarbeiter
 * @property-read User|null $user
 * @property-read File|null $file
 */
class BauhofNachricht extends ActiveRecord
{
    public const EIN = 'ein';
    public const AUS = 'aus';
    public const INTERN = 'intern';

    public static function tableName()
    {
        return 'bauhof_nachricht';
    }

    public function getMitarbeiter()
    {
        return $this->hasOne(Mitarbeiter::class, ['id' => 'mitarbeiter_id']);
    }

    public function getUser()
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }

    public function getFile()
    {
        return $this->hasOne(File::class, ['id' => 'file_id']);
    }

    public function isBild(): bool
    {
        return $this->file_id !== null && str_starts_with((string)$this->file?->mime_type, 'image/');
    }

    /** Ungelesene eingehende Nachrichten (für Hinweise im Menü und in der Liste) */
    public static function ungelesen(?int $contentId = null): int
    {
        return (int)static::find()->where(['richtung' => self::EIN, 'gelesen_at' => null])
            ->andFilterWhere(['content_id' => $contentId])->count();
    }

    public function beforeSave($insert)
    {
        if ($insert && !$this->created_at) {
            $this->created_at = date('Y-m-d H:i:s');
        }
        return parent::beforeSave($insert);
    }

    public function afterDelete()
    {
        parent::afterDelete();
        $this->file?->delete();
    }
}
