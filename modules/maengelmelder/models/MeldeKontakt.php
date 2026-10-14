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

    public function formName()
    {
        return 'Kontakt';
    }

    public function scenarios()
    {
        return [
            self::SCENARIO_GAST => ['vorname', 'nachname', 'email', 'telefon', 'datenschutz', 'captcha', 'website'],
            self::SCENARIO_KONTO => ['telefon'],
        ];
    }

    public function rules()
    {
        return [
            [['vorname', 'nachname', 'email', 'telefon', 'website'], 'trim'],
            [['vorname', 'nachname'], 'required', 'message' => 'Bitte {attribute} angeben.'],
            [['vorname', 'nachname'], 'string', 'max' => 60],
            [['vorname', 'nachname'], 'match', 'pattern' => '/^[\p{L}][\p{L} .\'-]*$/u', 'message' => 'Bitte einen gültigen {attribute} angeben.'],
            ['email', 'string', 'max' => 150],
            ['email', 'email', 'message' => 'Bitte eine gültige E-Mail-Adresse angeben.'],
            ['telefon', 'match', 'pattern' => '/^\+?[0-9][0-9 ()\/-]{4,28}$/', 'message' => 'Bitte eine gültige Telefonnummer angeben (nur Ziffern, Leerzeichen, + / - ( )).'],
            ['email', 'kontaktNoetig', 'skipOnEmpty' => false, 'on' => self::SCENARIO_GAST],
            ['datenschutz', 'compare', 'compareValue' => '1', 'message' => 'Bitte bestätigen Sie die Datenschutzhinweise.'],
            ['captcha', Yii::$app->captcha->getValidatorClass(), 'on' => self::SCENARIO_GAST],
        ];
    }

    public function kontaktNoetig($attribute)
    {
        if ($this->email === '' && $this->telefon === '') {
            $this->addError('email', 'Bitte mindestens eine E-Mail-Adresse oder eine Telefonnummer angeben, damit der Bauhof bei Rückfragen nachfragen kann.');
        }
    }

    public function attributeLabels()
    {
        return [
            'vorname' => 'Vorname',
            'nachname' => 'Nachname',
            'email' => 'E-Mail',
            'telefon' => 'Telefon',
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
