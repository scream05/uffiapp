<?php

namespace humhub\modules\vereine\models;

use humhub\modules\verzeichnis\models\VerwalterBasis;

/**
 * Wer einen Verein im Verzeichnis pflegen darf (Vorstand o. Ä.).
 *
 * @property int $verein_id
 * @property-read Verein $verein
 */
class VereinVerwalter extends VerwalterBasis
{
    public static function tableName()
    {
        return 'verein_verwalter';
    }

    public function getVerein()
    {
        return $this->hasOne(Verein::class, ['id' => 'verein_id']);
    }
}
