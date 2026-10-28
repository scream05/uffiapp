<?php

namespace humhub\modules\gewerbe\models;

use humhub\modules\content\models\Content;
use humhub\modules\gewerbe\Module;
use humhub\modules\user\models\User;
use humhub\modules\verzeichnis\models\BeitragBasis;

/**
 * Beitrag eines Betriebsverwalters (Betriebsseite und/oder Übersicht); Logik in modules/verzeichnis (BeitragBasis).
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
class BetriebBeitrag extends BeitragBasis
{
    public static function tableName()
    {
        return 'gewerbe_beitrag';
    }

    public static function kategorie(): string
    {
        return 'GewerbeModule.base';
    }

    public static function eintragRelation(): string
    {
        return 'betrieb';
    }

    public static function seiteSpalte(): string
    {
        return 'auf_betriebsseite';
    }

    public static function darfAllesVerwalten(): bool
    {
        return Module::canManageAll();
    }

    public function getBetrieb()
    {
        return $this->hasOne(Betrieb::class, ['id' => 'betrieb_id']);
    }
}
