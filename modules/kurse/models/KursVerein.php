<?php

namespace humhub\modules\kurse\models;

use humhub\components\ActiveRecord;

/**
 * Freischaltung eines Vereins für das Kursmodul.
 *
 * @property int $verein_id
 * @property string $created_at
 * @property int|null $created_by
 */
class KursVerein extends ActiveRecord
{
    public static function tableName()
    {
        return 'kurs_verein';
    }
}
