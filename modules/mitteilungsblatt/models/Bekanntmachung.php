<?php

namespace humhub\modules\mitteilungsblatt\models;

use Yii;
use yii\db\ActiveRecord;

/**
 * Eine amtliche Bekanntmachung im Amtsblatt (Mitteilungsblatt). Rechtsverbindlich ist nur die Originalseite.
 *
 * @property int $id
 * @property int $ausgabe_id
 * @property int $seite
 * @property string $titel
 * @property string $art
 * @property string|null $stelle
 * @property string|null $frist_bis
 * @property string|null $frist_text
 * @property int $personenbezug
 * @property int $ausgeblendet
 * @property string $created_at
 * @property-read Ausgabe $ausgabe
 */
class Bekanntmachung extends ActiveRecord
{
    public const ARTEN = ['satzung', 'verordnung', 'haushalt', 'bauleitplanung', 'bauen', 'verfahren', 'wahl', 'zustellung', 'sonstiges', 'hinweis'];

    /** Hinweise der Verwaltung (Fälligkeiten, Fundsachen, Schließtage …) stehen im Amtsblatt oft unter „Amtliche Bekanntmachung“, sind aber keine – nur auf Wunsch zeigen */
    public const NUR_AUF_WUNSCH = ['hinweis'];

    public static function tableName()
    {
        return 'mb_bekanntmachung';
    }

    public function getAusgabe()
    {
        return $this->hasOne(Ausgabe::class, ['id' => 'ausgabe_id']);
    }

    public static function artName(string $art): string
    {
        return match ($art) {
            'satzung' => Yii::t('MitteilungsblattModule.base', 'Satzung'),
            'verordnung' => Yii::t('MitteilungsblattModule.base', 'Verordnung'),
            'haushalt' => Yii::t('MitteilungsblattModule.base', 'Haushalt'),
            'bauleitplanung' => Yii::t('MitteilungsblattModule.base', 'Bauleitplanung'),
            'bauen' => Yii::t('MitteilungsblattModule.base', 'Bauen und Grundstücke'),
            'verfahren' => Yii::t('MitteilungsblattModule.base', 'Allgemeinverfügungen und Verfahren'),
            'wahl' => Yii::t('MitteilungsblattModule.base', 'Wahlen'),
            'hinweis' => Yii::t('MitteilungsblattModule.base', 'Hinweise der Verwaltung'),
            'zustellung' => Yii::t('MitteilungsblattModule.base', 'Öffentliche Zustellung'),
            default => Yii::t('MitteilungsblattModule.base', 'Sonstiges'),
        };
    }

    /** Frist läuft noch (bis einschließlich heute) */
    public function getFristLaeuft(): bool
    {
        return $this->frist_bis !== null && $this->frist_bis >= date('Y-m-d');
    }
}
