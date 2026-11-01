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
 * @property string|null $gemeldet_am ungenutzt (Push „Tagesordnung veröffentlicht“, bis 01.11.2026)
 * @property string $abgerufen_am
 */
class Sitzung extends ActiveRecord
{
    public static function tableName()
    {
        return 'stadtrat_sitzung';
    }

    /**
     * Symbol und Farbe je Gremium (Farben aus dem Erscheinungsbild der Stadt, Weiß darauf mindestens 4,5:1):
     * Stadtrat Stahlblau mit Rathaus, Finanz- und Werkausschuss Dunkelgrün mit Euro, Verwaltungs- und Bauausschuss
     * Bordeaux mit Gebäude, sonst Grau mit Personen. Unterscheidung nie nur über die Farbe, das Symbol ist immer dabei.
     * @return array{0: string, 1: string} [Font-Awesome-Name, Farbe]
     */
    public static function symbol(string $gremium): array
    {
        $g = mb_strtolower($gremium);
        return match (true) {
            str_contains($g, 'bau') => ['building', '#7c1922'],
            str_contains($g, 'finanz') || str_contains($g, 'werk') => ['eur', '#005828'],
            str_contains($g, 'stadtrat') => ['university', '#446e88'],
            default => ['users', '#505050'],
        };
    }

    /** Rundes Symbol des Gremiums (nur Schmuck, der Name steht immer daneben) */
    public static function symbolHtml(string $gremium, string $klasse = 'sr-symbol'): string
    {
        [$icon, $farbe] = self::symbol($gremium);
        return '<span class="' . $klasse . '" style="background:' . $farbe . '" aria-hidden="true"><i class="fa fa-' . $icon . '"></i></span>';
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
