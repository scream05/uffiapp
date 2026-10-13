<?php

namespace humhub\modules\maengelmelder\models;

use humhub\components\ActiveRecord;
use humhub\modules\content\models\Content;
use Yii;

/**
 * „Betrifft mich auch“ einer weiteren Person zu einer Meldung
 *
 * @property int $content_id
 * @property int $user_id
 * @property string $created_at
 */
class MaengelAuch extends ActiveRecord
{
    public static function tableName()
    {
        return 'maengel_auch';
    }

    /** @return array content_id => Anzahl */
    public static function counts(array $contentIds): array
    {
        if (!$contentIds) {
            return [];
        }
        return array_map('intval', static::find()->select(['n' => 'COUNT(*)', 'content_id'])
            ->where(['content_id' => $contentIds])->groupBy('content_id')->indexBy('content_id')->column());
    }

    /** @return int[] content_ids, bei denen der angemeldete Nutzer „auch“ gesagt hat */
    public static function mine(array $contentIds): array
    {
        if (!$contentIds || Yii::$app->user->isGuest) {
            return [];
        }
        return array_map('intval', static::find()->select('content_id')
            ->where(['content_id' => $contentIds, 'user_id' => Yii::$app->user->id])->column());
    }

    /** Anschließen bzw. wieder zurücknehmen; nicht bei eigenen Meldungen. Gibt den neuen Zustand zurück. */
    public static function toggle(Content $content, int $userId): bool
    {
        if ((int)$content->created_by === $userId) {
            return false;
        }
        $row = static::findOne(['content_id' => $content->id, 'user_id' => $userId]);
        if ($row) {
            $row->delete();
            return false;
        }
        (new static(['content_id' => $content->id, 'user_id' => $userId, 'created_at' => date('Y-m-d H:i:s')]))->save(false);
        return true;
    }
}
