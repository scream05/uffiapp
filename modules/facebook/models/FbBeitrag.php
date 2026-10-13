<?php

namespace humhub\modules\facebook\models;

use yii\db\ActiveRecord;

/**
 * @property int $id
 * @property string $fb_id
 * @property int|null $post_id
 * @property string|null $fb_updated
 * @property string $status
 * @property string $created_at
 */
class FbBeitrag extends ActiveRecord
{
    public static function tableName()
    {
        return 'fb_beitrag';
    }
}
