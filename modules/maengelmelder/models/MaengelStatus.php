<?php

namespace humhub\modules\maengelmelder\models;

use humhub\components\ActiveRecord;
use humhub\modules\content\models\Content;
use humhub\modules\maengelmelder\permissions\ManageStatus;
use humhub\modules\user\models\User;
use Yii;

/**
 * Aktueller Status einer Meldung (je Content). Ohne Eintrag gilt „Gemeldet“.
 *
 * @property int $content_id
 * @property string $status
 * @property string|null $note
 * @property int|null $updated_by
 * @property string $updated_at
 */
class MaengelStatus extends ActiveRecord
{
    /** Schlüssel => [Bezeichnung, Farbe] */
    public const STATUSES = [
        'gemeldet' => ['Gemeldet', '#1f78b4'],
        'pruefung' => ['In Prüfung', '#e08e0b'],
        'arbeit' => ['In Bearbeitung', '#d35400'],
        'erledigt' => ['Erledigt', '#2e8b57'],
        'abgelehnt' => ['Nicht zuständig / abgelehnt', '#7f8c8d'],
    ];

    /** Abgeschlossen: nicht mehr als „schon gemeldet“ vorschlagen */
    public const CLOSED = ['erledigt', 'abgelehnt'];

    public static function tableName()
    {
        return 'maengel_status';
    }

    public static function forContent(Content $content): self
    {
        return static::findOne(['content_id' => $content->id])
            ?? new static(['content_id' => $content->id, 'status' => 'gemeldet']);
    }

    public static function canManage(): bool
    {
        return !Yii::$app->user->isGuest
            && (Yii::$app->user->isAdmin() || Yii::$app->user->can(ManageStatus::class));
    }

    public function getLabel(): string
    {
        return self::STATUSES[$this->status][0] ?? $this->status;
    }

    public function getColor(): string
    {
        return self::STATUSES[$this->status][1] ?? '#777';
    }

    public function getEditor()
    {
        return $this->hasOne(User::class, ['id' => 'updated_by']);
    }
}
