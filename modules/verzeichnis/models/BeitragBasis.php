<?php

namespace humhub\modules\verzeichnis\models;

use humhub\components\ActiveRecord;
use humhub\interfaces\ViewableInterface;
use humhub\modules\content\models\Content;
use humhub\modules\file\models\File;
use humhub\modules\user\helpers\AuthHelper;
use humhub\modules\user\models\User;
use Yii;

/**
 * Beitrag eines Verwalters zu einem Verzeichniseintrag (Betrieb, Verein). Wird eingereicht, ein Admin gibt ihn frei
 * (Eintragsseite und/oder Übersicht) oder lehnt ihn mit Begründung ab. Bei „Übersicht“ entsteht ein normaler
 * HumHub-Beitrag im Space des Verzeichnisses (content_id), die Bilder wandern dorthin.
 *
 * @property int $id
 * @property int $user_id
 * @property string $text
 * @property string $status
 * @property int $uebersicht
 * @property int|null $content_id
 * @property string|null $begruendung
 * @property int|null $geprueft_von
 * @property string|null $geprueft_am
 * @property string $created_at
 * @property-read ActiveRecord $eintrag Betrieb bzw. Verein
 * @property-read User $user
 * @property-read Content|null $content
 */
abstract class BeitragBasis extends ActiveRecord implements ViewableInterface
{
    public const STATUS_EINGEREICHT = 'eingereicht';
    public const STATUS_VEROEFFENTLICHT = 'veroeffentlicht';
    public const STATUS_ABGELEHNT = 'abgelehnt';

    public const MAX_BILDER = 4;

    /** Übersetzungskategorie des Moduls, z. B. „GewerbeModule.base“ */
    abstract public static function kategorie(): string;

    /** Relation zum Eintrag, z. B. „betrieb“ (auch Name der Spalte <relation>_id und des Parameters in Formularen) */
    abstract public static function eintragRelation(): string;

    /** Spalte „auf der Eintragsseite zeigen“, z. B. „auf_betriebsseite“ */
    abstract public static function seiteSpalte(): string;

    /** Admins und alle mit dem Recht „… verwalten“ */
    abstract public static function darfAllesVerwalten(): bool;

    public function rules()
    {
        return [
            ['text', 'trim'],
            ['text', 'required', 'message' => Yii::t(static::kategorie(), 'Bitte einen Text schreiben.')],
            ['text', 'string', 'min' => 10, 'max' => 5000],
        ];
    }

    public function attributeLabels()
    {
        return ['text' => Yii::t(static::kategorie(), 'Text')];
    }

    public function beforeSave($insert)
    {
        if ($insert) {
            $this->created_at = date('Y-m-d H:i:s');
        }
        return parent::beforeSave($insert);
    }

    public function afterDelete()
    {
        foreach ($this->fileManager->findAll() as $file) {
            $file->delete();
        }
        parent::afterDelete();
    }

    /** Betrieb bzw. Verein */
    public function getEintrag()
    {
        return $this->{static::eintragRelation()};
    }

    public function getUser()
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }

    public function getContent()
    {
        return $this->hasOne(Content::class, ['id' => 'content_id']);
    }

    /** Wer Bilder des Beitrags sehen darf (file/download): veröffentlicht wie das Verzeichnis, sonst Verfasser und Admins */
    public function canView($user = null): bool
    {
        if ($this->status === self::STATUS_VEROEFFENTLICHT) {
            return !Yii::$app->user->isGuest || AuthHelper::isGuestAccessEnabled();
        }
        return !Yii::$app->user->isGuest && (Yii::$app->user->id === $this->user_id || static::darfAllesVerwalten());
    }

    /** @return File[] Bilder – nach Freigabe für die Übersicht hängen sie am HumHub-Beitrag */
    public function getBilder(): array
    {
        $record = $this->content?->getModel() ?? $this;
        return array_values(array_filter($record->fileManager->findAll(), fn($f) => str_starts_with((string)$f->mime_type, 'image/')));
    }

    public function getStatusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_EINGEREICHT => Yii::t(static::kategorie(), 'wartet auf Prüfung'),
            self::STATUS_ABGELEHNT => Yii::t(static::kategorie(), 'abgelehnt'),
            default => Yii::t(static::kategorie(), 'veröffentlicht'),
        };
    }

    public static function forContent(int $contentId): ?static
    {
        return static::findOne(['content_id' => $contentId]);
    }
}
