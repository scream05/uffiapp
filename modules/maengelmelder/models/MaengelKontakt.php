<?php

namespace humhub\modules\maengelmelder\models;

use humhub\components\ActiveRecord;
use humhub\modules\user\models\User;

/**
 * Kontaktdaten zu einer Meldung, nur für den Bauhof. Gäste: Vor- und Nachname, E-Mail und/oder Telefon.
 * Angemeldete: nur user_id und ggf. Telefon (Name und E-Mail kommen aus dem Konto).
 *
 * @property int $content_id
 * @property int|null $user_id
 * @property string|null $vorname
 * @property string|null $nachname
 * @property string|null $email
 * @property string|null $telefon
 * @property int $info_mail Über Statusänderungen per E-Mail informieren
 * @property int $info_whatsapp … per WhatsApp an wa_id
 * @property string|null $wa_id Telefon im WhatsApp-Format (nur Ziffern, mit Ländervorwahl)
 * @property string $created_at
 * @property-read User|null $user
 */
class MaengelKontakt extends ActiveRecord
{
    public static function tableName()
    {
        return 'maengel_kontakt';
    }

    public function getUser()
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }

    public function istGast(): bool
    {
        return $this->user_id === null;
    }

    public function getName(): string
    {
        return $this->user ? $this->user->displayName : trim("$this->vorname $this->nachname");
    }

    public function getMail(): ?string
    {
        return $this->user ? $this->user->email : ($this->email ?: null);
    }
}
