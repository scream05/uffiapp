<?php

namespace humhub\modules\gewerbe\models;

use humhub\components\ActiveRecord;
use humhub\interfaces\ViewableInterface;
use humhub\modules\content\models\Content;
use humhub\modules\file\models\File;
use humhub\modules\user\helpers\AuthHelper;
use humhub\modules\user\models\User;
use humhub\modules\gewerbe\Module;
use Yii;

/**
 * Beitrag eines Betriebsverwalters. Wird eingereicht, ein Admin gibt ihn frei (Betriebsseite und/oder Übersicht)
 * oder lehnt ihn mit Begründung ab. Bei „Übersicht“ entsteht ein normaler HumHub-Beitrag im Space des
 * Verzeichnisses (content_id), die Bilder wandern dorthin.
 *
 * @property int $id
 * @property int $betrieb_id
 * @property int $user_id
 * @property string $text
 * @property string $status
 * @property int $auf_betriebsseite
 * @property int $uebersicht
 * @property int|null $content_id
 * @property string|null $begruendung
 * @property int|null $geprueft_von
 * @property string|null $geprueft_am
 * @property string $created_at
 * @property-read Betrieb $betrieb
 * @property-read User $user
 * @property-read Content|null $content
 */
class BetriebBeitrag extends ActiveRecord implements ViewableInterface
{
    public const STATUS_EINGEREICHT = 'eingereicht';
    public const STATUS_VEROEFFENTLICHT = 'veroeffentlicht';
    public const STATUS_ABGELEHNT = 'abgelehnt';

    public const MAX_BILDER = 4;

    public static function tableName()
    {
        return 'gewerbe_beitrag';
    }

    public function rules()
    {
        return [
            ['text', 'trim'],
            ['text', 'required', 'message' => Yii::t('GewerbeModule.base', 'Bitte einen Text schreiben.')],
            ['text', 'string', 'min' => 10, 'max' => 5000],
        ];
    }

    public function attributeLabels()
    {
        return ['text' => Yii::t('GewerbeModule.base', 'Text')];
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

    public function getBetrieb()
    {
        return $this->hasOne(Betrieb::class, ['id' => 'betrieb_id']);
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
        return !Yii::$app->user->isGuest && (Yii::$app->user->id === $this->user_id || Module::canManageAll());
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
            self::STATUS_EINGEREICHT => Yii::t('GewerbeModule.base', 'wartet auf Prüfung'),
            self::STATUS_ABGELEHNT => Yii::t('GewerbeModule.base', 'abgelehnt'),
            default => Yii::t('GewerbeModule.base', 'veröffentlicht'),
        };
    }

    public static function forContent(int $contentId): ?self
    {
        return static::findOne(['content_id' => $contentId]);
    }
}
