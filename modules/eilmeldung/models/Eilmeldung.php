<?php

namespace humhub\modules\eilmeldung\models;

use humhub\components\ActiveRecord;
use humhub\modules\eilmeldung\Module;
use Yii;
use yii\helpers\Url;

/**
 * @property int $id
 * @property string $art sperrung | wasser | strom | unwetter | warnung | sonstiges
 * @property string $titel
 * @property string|null $text
 * @property string $gueltig_bis
 * @property string|null $beendet_am
 * @property string $quelle hand | nina
 * @property string|null $quelle_id
 * @property string|null $schwere Extreme | Severe | Moderate | Minor (NINA)
 * @property int $empfaenger Anzahl Konten, an die die Push-Nachricht ging
 * @property int|null $created_by
 * @property string $created_at
 */
class Eilmeldung extends ActiveRecord
{
    public static function tableName()
    {
        return 'eilmeldung';
    }

    public function rules()
    {
        return [
            [['art', 'titel', 'gueltig_bis'], 'required'],
            ['art', 'in', 'range' => array_keys(Module::ARTEN)],
            // „Amtliche Warnung“ nur aus NINA – eine eigene Meldung soll nicht wie eine Warnung einer Behörde aussehen
            ['art', 'compare', 'compareValue' => 'warnung', 'operator' => '!==', 'when' => fn($m) => $m->quelle !== 'nina',
                'message' => Yii::t('EilmeldungModule.base', '„Amtliche Warnung“ gibt es nur für Warnungen aus NINA.')],
            ['titel', 'string', 'max' => 150],
            ['text', 'string', 'max' => 2000],
            ['gueltig_bis', 'datetime', 'format' => 'php:Y-m-d H:i:s'],
        ];
    }

    public function attributeLabels()
    {
        return [
            'art' => Yii::t('EilmeldungModule.base', 'Art'),
            'titel' => Yii::t('EilmeldungModule.base', 'Kurz: Was ist los?'),
            'text' => Yii::t('EilmeldungModule.base', 'Einzelheiten (wo, wie lange, was tun)'),
            'gueltig_bis' => Yii::t('EilmeldungModule.base', 'Gilt bis'),
        ];
    }

    /** Laufende Eilmeldungen (nicht beendet, Gültigkeit nicht vorbei), neueste zuerst */
    public static function aktiv(): \yii\db\ActiveQuery
    {
        return static::find()->where(['beendet_am' => null])->andWhere(['>', 'gueltig_bis', date('Y-m-d H:i:s')])
            ->orderBy(['created_at' => SORT_DESC]);
    }

    public function istAktiv(): bool
    {
        return $this->beendet_am === null && $this->gueltig_bis > date('Y-m-d H:i:s');
    }

    public function getSymbol(): string
    {
        return Module::ARTEN[$this->art][1] ?? 'info-circle';
    }

    public function getUrl(bool $scheme = false): string
    {
        return Url::to(['/eilmeldung/index', '#' => 'eilmeldung-' . $this->id], $scheme);
    }
}
