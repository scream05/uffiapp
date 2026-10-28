<?php

namespace humhub\modules\gewerbe\models;

use humhub\modules\verzeichnis\models\VerwalterBasis;

/**
 * Wer einen Betrieb im Verzeichnis pflegen darf (Inhaber, Mitarbeitende mit Auftrag).
 *
 * @property int $betrieb_id
 * @property-read Betrieb $betrieb
 */
class BetriebVerwalter extends VerwalterBasis
{
    public static function tableName()
    {
        return 'gewerbe_verwalter';
    }

    public function getBetrieb()
    {
        return $this->hasOne(Betrieb::class, ['id' => 'betrieb_id']);
    }
}
