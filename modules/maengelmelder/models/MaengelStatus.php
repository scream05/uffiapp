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
    /** Schlüssel => [Bezeichnung, Farbe (Kartenpunkt, Stadtfarben wie resources/status/status.css), Symbol] */
    public const STATUSES = [
        'gemeldet' => ['Gemeldet', '#446e88', 'flag'],
        'pruefung' => ['In Prüfung', '#b98a00', 'search'],
        'arbeit' => ['In Bearbeitung', '#0f7a9e', 'wrench'],
        'erledigt' => ['Erledigt', '#6a8f0c', 'check'],
        'abgelehnt' => ['Nicht zuständig / abgelehnt', '#7f8c8d', 'ban'],
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

    /** Deutsche Bezeichnung (für Kommentare, Mails, Bauhof-Verlauf, KI-Assistent – gespeicherte Texte bleiben deutsch) */
    public function getLabel(): string
    {
        return self::STATUSES[$this->status][0] ?? $this->status;
    }

    /** Übersetzte Bezeichnung für die Anzeige (Karte, Etikett) */
    public function getAnzeigeLabel(): string
    {
        return self::anzeigeLabel($this->status);
    }

    public static function anzeigeLabel(string $status): string
    {
        return isset(self::STATUSES[$status]) ? Yii::t('MaengelmelderModule.base', self::STATUSES[$status][0]) : $status;
    }

    public function getColor(): string
    {
        return self::STATUSES[$this->status][1] ?? '#777';
    }

    /** Etikett (HTML) für einen Status-Schlüssel; Gestaltung in StatusAsset */
    public static function etikett(string $status): string
    {
        $icon = self::STATUSES[$status][2] ?? 'circle';
        $label = self::anzeigeLabel($status);
        return '<span class="mm-status mm-status-' . \humhub\helpers\Html::encode($status) . '">'
            . '<i class="fa fa-' . $icon . '" aria-hidden="true"></i>' . \humhub\helpers\Html::encode($label) . '</span>';
    }

    public function getEtikett(): string
    {
        return self::etikett($this->status);
    }

    public function getEditor()
    {
        return $this->hasOne(User::class, ['id' => 'updated_by']);
    }
}
