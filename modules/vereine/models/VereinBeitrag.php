<?php

namespace humhub\modules\vereine\models;

use humhub\modules\content\models\Content;
use humhub\modules\user\models\User;
use humhub\modules\vereine\Module;
use humhub\modules\verzeichnis\models\BeitragBasis;

/**
 * Beitrag eines Vereinsverwalters (Vereinsseite und/oder Übersicht); Logik in modules/verzeichnis (BeitragBasis).
 *
 * @property int $id
 * @property int $verein_id
 * @property int $user_id
 * @property string $text
 * @property string $status
 * @property int $auf_vereinsseite
 * @property int $uebersicht
 * @property int|null $content_id
 * @property string|null $begruendung
 * @property int|null $geprueft_von
 * @property string|null $geprueft_am
 * @property string $created_at
 * @property-read Verein $verein
 * @property-read User $user
 * @property-read Content|null $content
 */
class VereinBeitrag extends BeitragBasis
{
    public static function tableName()
    {
        return 'verein_beitrag';
    }

    public static function kategorie(): string
    {
        return 'VereineModule.base';
    }

    public static function eintragRelation(): string
    {
        return 'verein';
    }

    public static function seiteSpalte(): string
    {
        return 'auf_vereinsseite';
    }

    public static function darfAllesVerwalten(): bool
    {
        return Module::canManageAll();
    }

    public function getVerein()
    {
        return $this->hasOne(Verein::class, ['id' => 'verein_id']);
    }
}
