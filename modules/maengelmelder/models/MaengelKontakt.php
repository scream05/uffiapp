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
 * @property string|null $sprache beim Melden eingestellte Sprache (Gäste); Angemeldete: Sprache des Kontos
 * @property int $info_mail Über Statusänderungen per E-Mail informieren
 * @property int $info_whatsapp … per WhatsApp an wa_id
 * @property string|null $wa_id Telefon im WhatsApp-Format (nur Ziffern, mit Ländervorwahl)
 * @property string|null $mail_token Bestätigungslink für die E-Mail-Adresse (Gäste)
 * @property string|null $mail_bestaetigt wann die E-Mail-Adresse bestätigt wurde
 * @property string|null $wa_bestaetigt wann die Nummer „START <Nr>“ geschickt hat
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

    /** Sprache für Mails an die meldende Person */
    public function getSprache(): string
    {
        return ($this->user ? $this->user->language : $this->sprache) ?: (\Yii::$app->settings->get('defaultLanguage') ?: 'de');
    }

    /** $fn in der Sprache der meldenden Person ausführen (Mailtexte), danach die bisherige Sprache wiederherstellen */
    public function inSprache(callable $fn)
    {
        $alt = [\Yii::$app->language, \Yii::$app->formatter->locale];
        \Yii::$app->language = \Yii::$app->formatter->locale = $this->getSprache();
        try {
            return $fn();
        } finally {
            [\Yii::$app->language, \Yii::$app->formatter->locale] = $alt;
        }
    }

    public function getMail(): ?string
    {
        return $this->user ? $this->user->email : ($this->email ?: null);
    }

    /** E-Mail-Adresse bestätigt? Bei Angemeldeten immer (bei der Registrierung bestätigt), bei Gästen über den Link */
    public function mailBestaetigt(): bool
    {
        return $this->user_id !== null || $this->mail_bestaetigt !== null;
    }

    /** Hat die Nummer selbst „START <Nr>“ geschrieben? Erst dann schicken wir WhatsApp-Nachrichten. */
    public function waBestaetigt(): bool
    {
        return $this->wa_id !== null && $this->wa_bestaetigt !== null;
    }
}
