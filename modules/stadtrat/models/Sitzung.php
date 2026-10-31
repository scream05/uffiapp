<?php

namespace humhub\modules\stadtrat\models;

use yii\db\ActiveRecord;

/**
 * @property int $id Kennung im RIS
 * @property string $gremium
 * @property string $titel
 * @property string $datum Y-m-d
 * @property string|null $beginn H:i
 * @property string|null $ort
 * @property int $nichtoeffentlich 1 = danach nichtöffentlicher Teil
 * @property string|null $tagesordnung JSON [[nr, titel], …]
 * @property string|null $hinweise JSON [[überschrift, text], …] (z. B. Bürgerfragestunde)
 * @property string|null $ris_stand Änderungszeit im RIS (opdatetime), nur bei Änderung neu abrufen
 * @property string|null $gemeldet_am Push „Tagesordnung veröffentlicht“ verschickt (oder beim ersten Abruf übersprungen)
 * @property string $abgerufen_am
 */
class Sitzung extends ActiveRecord
{
    public static function tableName()
    {
        return 'stadtrat_sitzung';
    }

    /** @return array[] [[nr, titel], …] */
    public function getPunkte(): array
    {
        return json_decode((string)$this->tagesordnung, true) ?: [];
    }

    /** @return array[] [[überschrift, text], …] */
    public function getHinweise(): array
    {
        return json_decode((string)$this->hinweise, true) ?: [];
    }

    public function istKommend(): bool
    {
        return $this->datum >= date('Y-m-d');
    }

    public static function kommende(): \yii\db\ActiveQuery
    {
        return self::find()->where(['>=', 'datum', date('Y-m-d')])->orderBy(['datum' => SORT_ASC, 'beginn' => SORT_ASC]);
    }

    public static function vergangene(): \yii\db\ActiveQuery
    {
        return self::find()->where(['<', 'datum', date('Y-m-d')])->orderBy(['datum' => SORT_DESC, 'beginn' => SORT_DESC]);
    }

    /** „Do., 19.11.2026“ */
    public function tag(): string
    {
        return ['So.', 'Mo.', 'Di.', 'Mi.', 'Do.', 'Fr.', 'Sa.'][(int)date('w', strtotime($this->datum))] . ', ' . date('j.n.Y', strtotime($this->datum));
    }
}
