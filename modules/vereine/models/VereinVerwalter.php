<?php

namespace humhub\modules\vereine\models;

use humhub\components\ActiveRecord;
use humhub\modules\user\models\User;

/**
 * @property int $verein_id
 * @property int $user_id
 * @property string $status angefragt | bestaetigt
 * @property string|null $nachricht
 * @property string $created_at
 * @property-read Verein $verein
 * @property-read User $user
 */
class VereinVerwalter extends ActiveRecord
{
    public const STATUS_ANGEFRAGT = 'angefragt';
    public const STATUS_BESTAETIGT = 'bestaetigt';

    public static function tableName()
    {
        return 'verein_verwalter';
    }

    public function beforeSave($insert)
    {
        if ($insert) {
            $this->created_at = date('Y-m-d H:i:s');
        }
        return parent::beforeSave($insert);
    }

    public function getVerein()
    {
        return $this->hasOne(Verein::class, ['id' => 'verein_id']);
    }

    public function getUser()
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }
}
