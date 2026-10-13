<?php

namespace humhub\modules\maengelmelder\models;

use humhub\components\ActiveRecord;
use humhub\modules\user\models\User;

/**
 * Bearbeitung einer Meldung durch den Bauhof (nur intern): zuständiger Mitarbeiter, Priorität, Frist, Notizen.
 *
 * @property int $content_id
 * @property int|null $mitarbeiter_id
 * @property string $prioritaet
 * @property string|null $faellig
 * @property string|null $hinweis
 * @property string|null $notiz
 * @property string|null $zugewiesen_at
 * @property int|null $zugewiesen_by
 * @property string|null $gesendet_at
 * @property string $updated_at
 * @property-read Mitarbeiter|null $mitarbeiter
 * @property-read User|null $zuweiser
 */
class Auftrag extends ActiveRecord
{
    public const PRIORITAETEN = [
        'normal' => ['Normal', '#7f8c8d'],
        'hoch' => ['Hoch', '#e08e0b'],
        'dringend' => ['Dringend', '#c0392b'],
    ];

    public static function tableName()
    {
        return 'maengel_auftrag';
    }

    public static function forContentId(int $contentId): self
    {
        return static::findOne($contentId) ?? new static(['content_id' => $contentId, 'prioritaet' => 'normal']);
    }

    public function rules()
    {
        return [
            [['hinweis', 'notiz'], 'trim'],
            [['hinweis', 'notiz'], 'string', 'max' => 2000],
            ['prioritaet', 'in', 'range' => array_keys(self::PRIORITAETEN)],
            ['faellig', 'date', 'format' => 'php:Y-m-d'],
            ['mitarbeiter_id', 'exist', 'targetClass' => Mitarbeiter::class, 'targetAttribute' => 'id', 'filter' => ['aktiv' => 1]],
            [['faellig', 'hinweis', 'notiz', 'mitarbeiter_id'], 'default', 'value' => null],
        ];
    }

    public function attributeLabels()
    {
        return [
            'mitarbeiter_id' => 'Zuständig',
            'prioritaet' => 'Priorität',
            'faellig' => 'Erledigen bis',
            'hinweis' => 'Hinweis für den Mitarbeiter (geht per WhatsApp mit)',
            'notiz' => 'Interne Notiz (nur Bauhof)',
        ];
    }

    public function getMitarbeiter()
    {
        return $this->hasOne(Mitarbeiter::class, ['id' => 'mitarbeiter_id']);
    }

    public function getZuweiser()
    {
        return $this->hasOne(User::class, ['id' => 'zugewiesen_by']);
    }

    public function getPrioritaetLabel(): string
    {
        return self::PRIORITAETEN[$this->prioritaet][0] ?? $this->prioritaet;
    }

    public function beforeSave($insert)
    {
        $this->updated_at = date('Y-m-d H:i:s');
        return parent::beforeSave($insert);
    }
}
