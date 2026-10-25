<?php

namespace humhub\modules\vereine\models;

use humhub\components\ActiveRecord;
use Yii;

/**
 * Mannschaft eines Vereins beim BFV bzw. einer anderen Spielquelle (components/Bfv.php, components/Spielquellen.php)
 *
 * @property string $id permanentId beim BFV; bei den anderen Quellen „<quelle>:<Kennung>“, z. B. „dbb:285782“
 * @property int $verein_id
 * @property string $name
 * @property string $typ Herren, A-Junioren, … bzw. „U14 männlich“
 * @property string|null $zusatz unterscheidet Mannschaften gleicher Bezeichnung, z. B. „Landesliga“
 * @property string|null $liga
 * @property string|null $liga_id
 * @property string|null $tabelle JSON [{platz,name,spiele,s,u,n,diff,punkte,eigen}]
 * @property string $aktualisiert_am
 */
class VereinMannschaft extends ActiveRecord
{
    /** Reihenfolge der Mannschaften: erst Erwachsene, dann die Jugend von alt nach jung */
    public const REIHENFOLGE = ['Herren', 'Frauen', 'Damen', 'Erwachsene', 'Senioren', 'Alte Herren', 'A-Junioren', 'B-Junioren', 'B-Juniorinnen',
        'C-Junioren', 'C-Juniorinnen', 'D-Junioren', 'D-Juniorinnen', 'E-Junioren', 'F-Junioren', 'G-Junioren'];

    public static function tableName()
    {
        return 'verein_mannschaft';
    }

    public function getVerein()
    {
        return $this->hasOne(Verein::class, ['id' => 'verein_id']);
    }

    public function getSpiele()
    {
        return $this->hasMany(VereinSpiel::class, ['mannschaft_id' => 'id']);
    }

    /** Quelle einer Mannschaftskennung: BFV-Kennungen haben keinen Vorsatz */
    public static function quelleVon(string $id): string
    {
        return preg_match('/^([a-z]+):/', $id, $m) ? $m[1] : 'bfv';
    }

    public function getQuelle(): string
    {
        return self::quelleVon($this->id);
    }

    /** z. B. „Herren“, „Herren II“, „E-Junioren I“, „A-Junioren (SG)“, „U14 männlich“, „Frauen (Landesliga)“ */
    public function getLabel(): string
    {
        // „U14 männlich“: Altersklasse bleibt, das Geschlecht wird übersetzt
        $label = preg_match('/^(U\d+) (.+)$/', $this->typ, $u)
            ? $u[1] . ' ' . Yii::t('VereineModule.base', $u[2])
            : Yii::t('VereineModule.base', $this->typ);
        if (preg_match('/\s(I{1,3}|IV)$/', $this->name, $m)) {
            $label .= ' ' . $m[1];
        }
        if (str_starts_with($this->name, '(SG)')) {
            $label .= ' (' . Yii::t('VereineModule.base', 'SG') . ')';
        }
        if ($this->zusatz) {
            $label .= ' (' . $this->zusatz . ')';
        }
        return $label;
    }

    /** @return array[] Tabelle der Liga (leer, wenn es keine gibt) */
    public function getTabelleList(): array
    {
        return json_decode((string)$this->tabelle, true) ?: [];
    }

    /** Eigene Zeile in der Tabelle */
    public function getPlatz(): ?array
    {
        foreach ($this->getTabelleList() as $zeile) {
            if (!empty($zeile['eigen'])) {
                return $zeile;
            }
        }
        return null;
    }

    /** @param self[] $mannschaften */
    public static function sortieren(array $mannschaften): array
    {
        usort($mannschaften, function (self $a, self $b) {
            // U-Mannschaften nach den Erwachsenen, ältere zuerst
            $pos = fn(self $m) => ($i = array_search($m->typ, self::REIHENFOLGE, true)) !== false ? $i
                : (preg_match('/^U(\d+)/', $m->typ, $u) ? 100 - (int)$u[1] : 99);
            return [$pos($a), $a->getLabel()] <=> [$pos($b), $b->getLabel()];
        });
        return $mannschaften;
    }
}
