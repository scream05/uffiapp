<?php

namespace humhub\modules\gewerbe\models;

use humhub\components\ActiveRecord;
use humhub\modules\user\models\User;

/**
 * Wer einen Betrieb im Verzeichnis pflegen darf (Inhaber, Mitarbeitende mit Auftrag).
 *
 * @property int $betrieb_id
 * @property int $user_id
 * @property string $status angefragt | bestaetigt
 * @property string|null $nachricht Funktion im Betrieb, z. B. „Inhaberin“
 * @property string $created_at
 */
class BetriebVerwalter extends ActiveRecord
{
    public const STATUS_ANGEFRAGT = 'angefragt';
    public const STATUS_BESTAETIGT = 'bestaetigt';

    public static function tableName()
    {
        return 'gewerbe_verwalter';
    }

    public function beforeSave($insert)
    {
        if ($insert) {
            $this->created_at = date('Y-m-d H:i:s');
        }
        return parent::beforeSave($insert);
    }

    public function getUser()
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }

    public function getBetrieb()
    {
        return $this->hasOne(Betrieb::class, ['id' => 'betrieb_id']);
    }
}
