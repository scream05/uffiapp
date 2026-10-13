<?php

namespace humhub\modules\mitteilungsblatt\models;

use yii\db\ActiveRecord;

/**
 * @property int $id
 * @property int $ausgabe_id
 * @property int $seite
 * @property string $text
 */
class Seite extends ActiveRecord
{
    public static function tableName()
    {
        return 'mb_seite';
    }
}
