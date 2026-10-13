<?php

namespace humhub\modules\kiAssistent\models;

use humhub\components\ActiveRecord;

/**
 * Bewertung einer Antwort (anonym, ohne Konto)
 *
 * @property int $id
 * @property int $bewertung 1 = hilfreich, -1 = nicht hilfreich
 * @property string $frage
 * @property string $antwort
 * @property string|null $kommentar
 * @property bool $erledigt
 * @property string $created_at
 */
class Feedback extends ActiveRecord
{
    /** Bewertungen werden nach dieser Zeit gelöscht */
    public const KEEP_DAYS = 180;

    public static function tableName()
    {
        return 'ki_feedback';
    }

    public static function cleanup(): void
    {
        static::deleteAll(['<', 'created_at', date('Y-m-d H:i:s', strtotime('-' . self::KEEP_DAYS . ' days'))]);
    }
}
