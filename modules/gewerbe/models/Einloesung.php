<?php

namespace humhub\modules\gewerbe\models;

use humhub\components\ActiveRecord;

/**
 * Eine Einlösung eines Gutscheins. Die Person (user_id) braucht es nur für das Limit „pro Person“;
 * der Betrieb sieht Zeitpunkt und Kurzcode, nie die Person.
 *
 * @property int $id
 * @property int $gutschein_id
 * @property int $betrieb_id
 * @property int $user_id
 * @property string $code 4 Zeichen, steht auf dem Bestätigungsbildschirm und in der Liste des Betriebs
 * @property string $created_at
 * @property string|null $storniert_am
 * @property-read Gutschein $gutschein
 * @property-read Betrieb $betrieb
 */
class Einloesung extends ActiveRecord
{
    /** Ohne leicht verwechselbare Zeichen (0/O, 1/I/L) */
    public const ZEICHEN = '23456789ABCDEFGHJKMNPQRSTUVWXYZ';

    public static function tableName()
    {
        return 'gewerbe_einloesung';
    }

    public function getGutschein()
    {
        return $this->hasOne(Gutschein::class, ['id' => 'gutschein_id']);
    }

    public function getBetrieb()
    {
        return $this->hasOne(Betrieb::class, ['id' => 'betrieb_id']);
    }

    public static function neuerCode(): string
    {
        $code = '';
        for ($i = 0; $i < 4; $i++) {
            $code .= self::ZEICHEN[random_int(0, strlen(self::ZEICHEN) - 1)];
        }
        return $code;
    }

    /** Sekunden seit der Einlösung */
    public function alter(): int
    {
        return time() - strtotime($this->created_at);
    }
}
