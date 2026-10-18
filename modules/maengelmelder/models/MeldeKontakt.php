<?php

namespace humhub\modules\maengelmelder\models;

use Yii;
use yii\base\Model;

/**
 * Kontaktangaben im Meldeformular. Gäste: Vor- und Nachname, mindestens E-Mail oder Telefon, Datenschutz-Häkchen,
 * Captcha (Altcha, ohne Fremddienst) und ein verstecktes Feld „website“, das nur Bots ausfüllen.
 * Angemeldete: nur die freiwillige Telefonnummer (Name und E-Mail kommen aus dem Konto).
 */
class MeldeKontakt extends Model
{
    public const SCENARIO_GAST = 'gast';
    public const SCENARIO_KONTO = 'konto';

    public $vorname = '';
    public $nachname = '';
    public $email = '';
    public $telefon = '';
    public $datenschutz = false;
    public $captcha;
    public $website = '';
    public $info_mail = false;
    public $info_whatsapp = false;

    public function formName()
    {
        return 'Kontakt';
    }

    public function scenarios()
    {
        return [
            self::SCENARIO_GAST => ['vorname', 'nachname', 'email', 'telefon', 'datenschutz', 'captcha', 'website', 'info_mail', 'info_whatsapp'],
            self::SCENARIO_KONTO => ['telefon', 'info_mail', 'info_whatsapp'],
        ];
    }

    public function rules()
    {
        return [
            [['vorname', 'nachname', 'email', 'telefon', 'website'], 'trim'],
            [['vorname', 'nachname'], 'required', 'message' => Yii::t('MaengelmelderModule.base', 'Bitte {attribute} angeben.')],
            [['vorname', 'nachname'], 'string', 'max' => 60],
            [['vorname', 'nachname'], 'match', 'pattern' => '/^[\p{L}][\p{L} .\'-]*$/u', 'message' => Yii::t('MaengelmelderModule.base', 'Bitte einen gültigen {attribute} angeben.')],
            ['email', 'string', 'max' => 150],
            ['email', 'email', 'message' => Yii::t('MaengelmelderModule.base', 'Bitte eine gültige E-Mail-Adresse angeben.')],
            ['telefon', 'match', 'pattern' => '/^\+?[0-9][0-9 ()\/-]{4,28}$/', 'message' => Yii::t('MaengelmelderModule.base', 'Bitte eine gültige Telefonnummer angeben (nur Ziffern, Leerzeichen, + / - ( )).')],
            ['email', 'kontaktNoetig', 'skipOnEmpty' => false, 'on' => self::SCENARIO_GAST],
            ['datenschutz', 'compare', 'compareValue' => '1', 'message' => Yii::t('MaengelmelderModule.base', 'Bitte bestätige die Datenschutzhinweise.')],
            ['captcha', Yii::$app->captcha->getValidatorClass(), 'on' => self::SCENARIO_GAST],
            [['info_mail', 'info_whatsapp'], 'boolean'],
            ['info_mail', 'infoMailPruefen'],
            ['info_whatsapp', 'infoWhatsappPruefen'],
        ];
    }

    public function infoMailPruefen($attribute)
    {
        if ($this->info_mail && $this->scenario === self::SCENARIO_GAST && $this->email === '') {
            $this->addError('info_mail', Yii::t('MaengelmelderModule.base', 'Für Infos per E-Mail bitte deine E-Mail-Adresse angeben.'));
        }
    }

    public function infoWhatsappPruefen($attribute)
    {
        if ($this->info_whatsapp && self::waId($this->telefon) === null) {
            $this->addError('info_whatsapp', Yii::t('MaengelmelderModule.base', 'Für Infos per WhatsApp bitte deine Handynummer angeben (z. B. 0171 1234567 oder +49 171 1234567).'));
        }
    }

    /**
     * Telefonnummer ins WhatsApp-Format (nur Ziffern mit Ländervorwahl): 0171 123 → 49171123, +43… / 0043… bleibt.
     * Deutsche Festnetznummern (nicht 015x/016x/017x) haben kein WhatsApp: null.
     */
    public static function waId(?string $telefon): ?string
    {
        $t = trim((string)$telefon);
        if ($t === '') {
            return null;
        }
        $ziffern = preg_replace('/\D/', '', $t);
        if (str_starts_with($t, '+')) {
            $nr = $ziffern;
        } elseif (str_starts_with($ziffern, '00')) {
            $nr = substr($ziffern, 2);
        } elseif (str_starts_with($ziffern, '0')) {
            $nr = '49' . substr($ziffern, 1);
        } else {
            return null;
        }
        if (strlen($nr) < 9 || strlen($nr) > 15) {
            return null;
        }
        if (str_starts_with($nr, '49') && !preg_match('/^491[567]\d{7,10}$/', $nr)) {
            return null;
        }
        return $nr;
    }

    public function kontaktNoetig($attribute)
    {
        if ($this->email === '' && $this->telefon === '') {
            $this->addError('email', Yii::t('MaengelmelderModule.base', 'Bitte mindestens eine E-Mail-Adresse oder eine Telefonnummer angeben, damit der Bauhof bei Rückfragen nachfragen kann.'));
        }
    }

    public function attributeLabels()
    {
        return [
            'vorname' => Yii::t('MaengelmelderModule.base', 'Vorname'),
            'nachname' => Yii::t('MaengelmelderModule.base', 'Nachname'),
            'email' => Yii::t('MaengelmelderModule.base', 'E-Mail'),
            'telefon' => Yii::t('MaengelmelderModule.base', 'Telefon'),
        ];
    }

    /** Verstecktes Feld ausgefüllt: sehr wahrscheinlich ein Bot */
    public function istBot(): bool
    {
        return $this->scenario === self::SCENARIO_GAST && $this->website !== '';
    }

    /** Vorbelegung des Telefons aus dem Profil (Handy, sonst privat), falls dort eine Nummer steht */
    public static function telefonAusProfil(\humhub\modules\user\models\User $user): string
    {
        $profile = $user->profile;
        foreach (['mobile', 'phone_private'] as $feld) {
            if ($profile->hasAttribute($feld) && trim((string)$profile->$feld) !== '') {
                return trim((string)$profile->$feld);
            }
        }
        return '';
    }
}
