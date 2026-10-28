<?php

namespace humhub\modules\verzeichnis\models;

use humhub\components\ActiveRecord;
use humhub\modules\user\models\User;

/**
 * Wer einen Verzeichniseintrag (Betrieb, Verein) pflegen darf.
 *
 * @property int $user_id
 * @property string $status angefragt | bestaetigt
 * @property string|null $nachricht Funktion, z. B. „Inhaberin“ oder „Vorstand“
 * @property string $created_at
 * @property-read User $user
 */
abstract class VerwalterBasis extends ActiveRecord
{
    public const STATUS_ANGEFRAGT = 'angefragt';
    public const STATUS_BESTAETIGT = 'bestaetigt';

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
}
