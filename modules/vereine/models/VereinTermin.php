<?php

namespace humhub\modules\vereine\models;

use humhub\components\ActiveRecord;

/**
 * Verknüpfung Kalendereintrag (gemeinsamer Kalender) ↔ Verein
 *
 * @property int $calendar_entry_id
 * @property int $verein_id
 */
class VereinTermin extends ActiveRecord
{
    public static function tableName()
    {
        return 'verein_termin';
    }
}
