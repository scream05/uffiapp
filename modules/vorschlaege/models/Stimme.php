<?php

namespace humhub\modules\vorschlaege\models;

use humhub\components\ActiveRecord;
use Yii;

/**
 * Stimme einer Person zu einem Vorschlag: +1 dafür, -1 dagegen
 *
 * @property int $content_id
 * @property int $user_id
 * @property int $wert
 * @property string $created_at
 */
class Stimme extends ActiveRecord
{
    public static function tableName()
    {
        return 'vorschlag_stimme';
    }

    /** @return array content_id => ['dafuer' => n, 'dagegen' => n] */
    public static function summen(array $contentIds): array
    {
        $ergebnis = [];
        if (!$contentIds) {
            return $ergebnis;
        }
        $rows = static::find()->select(['content_id', 'dafuer' => 'SUM(wert > 0)', 'dagegen' => 'SUM(wert < 0)'])
            ->where(['content_id' => $contentIds])->groupBy('content_id')->asArray()->all();
        foreach ($rows as $row) {
            $ergebnis[(int)$row['content_id']] = ['dafuer' => (int)$row['dafuer'], 'dagegen' => (int)$row['dagegen']];
        }
        return $ergebnis;
    }

    /** @return array content_id => eigener Wert (+1/-1) des angemeldeten Nutzers */
    public static function meine(array $contentIds): array
    {
        if (!$contentIds || Yii::$app->user->isGuest) {
            return [];
        }
        return array_map('intval', static::find()->select(['wert', 'content_id'])
            ->where(['content_id' => $contentIds, 'user_id' => Yii::$app->user->id])->indexBy('content_id')->column());
    }

    /** Abstimmen; dieselbe Stimme noch einmal nimmt sie zurück. Gibt den neuen eigenen Wert zurück (0 = keine). */
    public static function setzen(int $contentId, int $userId, int $wert): int
    {
        $wert = $wert > 0 ? 1 : -1;
        $row = static::findOne(['content_id' => $contentId, 'user_id' => $userId]);
        if ($row !== null && (int)$row->wert === $wert) {
            $row->delete();
            return 0;
        }
        $row ??= new static(['content_id' => $contentId, 'user_id' => $userId]);
        $row->wert = $wert;
        $row->created_at = date('Y-m-d H:i:s');
        $row->save(false);
        return $wert;
    }
}
