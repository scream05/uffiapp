<?php

namespace humhub\modules\kurse\models;

use humhub\components\ActiveRecord;
use Yii;

/**
 * Anmeldung eines Kindes zu einem Kurs (durch Erziehungsberechtigte, ohne Plattform-Konto).
 *
 * Ablauf: unbestaetigt (E-Mail noch nicht bestätigt) → platz oder warteliste → ggf. storniert.
 * Von Hand eingetragene Anmeldungen (z. B. Papierformular) überspringen die Bestätigung.
 *
 * @property int $id
 * @property int $kurs_id
 * @property string $token
 * @property string $status
 * @property string $kind_vorname
 * @property string $kind_nachname
 * @property string|null $kind_geburtsdatum
 * @property string|null $schwimmstand
 * @property string $eltern_name
 * @property string|null $email
 * @property string|null $telefon
 * @property string|null $notfall_telefon
 * @property bool $mitglied
 * @property string|null $zahlart
 * @property string|null $betrag
 * @property string|null $bezahlt_am
 * @property string|null $notiz
 * @property bool $von_hand
 * @property int|null $user_id
 * @property string $created_at
 * @property string|null $bestaetigt_am
 * @property string|null $platz_am
 * @property string|null $storniert_am
 * @property string|null $storniert_von eltern | verein | absage | voll | doppelt (bei der Bestätigung gab es schon eine bestätigte Anmeldung für das Kind)
 * @property string|null $erinnerung_am
 * @property string|null $zahlungserinnerung_am
 * @property string|null $anonymisiert_am
 * @property-read Kurs $kurs
 */
class Anmeldung extends ActiveRecord
{
    public const STATUS_UNBESTAETIGT = 'unbestaetigt';
    public const STATUS_PLATZ = 'platz';
    public const STATUS_WARTELISTE = 'warteliste';
    public const STATUS_STORNIERT = 'storniert';

    public const ZAHLART_BAR = 'bar';
    public const ZAHLART_UEBERWEISUNG = 'ueberweisung';

    public const SCENARIO_ELTERN = 'eltern';
    public const SCENARIO_HAND = 'hand';

    /** Pflicht-Häkchen im Formular */
    public $bedingungen_ok;
    public $datenschutz_ok;
    /** Falle für Bots (muss leer bleiben) */
    public $webseite;

    public static function tableName()
    {
        return 'kurs_anmeldung';
    }

    public function scenarios()
    {
        $felder = ['kind_vorname', 'kind_nachname', 'kind_geburtsdatum', 'schwimmstand', 'eltern_name', 'email', 'telefon',
            'notfall_telefon', 'mitglied', 'zahlart'];
        return [
            self::SCENARIO_DEFAULT => $felder,
            self::SCENARIO_ELTERN => array_merge($felder, ['bedingungen_ok', 'datenschutz_ok', 'webseite']),
            self::SCENARIO_HAND => array_merge($felder, ['notiz']),
        ];
    }

    public function rules()
    {
        return [
            [['kind_vorname', 'kind_nachname', 'kind_geburtsdatum', 'schwimmstand', 'eltern_name', 'email', 'telefon',
                'notfall_telefon', 'notiz'], 'trim'],
            [['kind_vorname', 'kind_nachname', 'eltern_name'], 'required'],
            [['kind_geburtsdatum', 'email', 'telefon'], 'required', 'on' => self::SCENARIO_ELTERN],
            [['kind_vorname', 'kind_nachname'], 'string', 'max' => 100],
            ['eltern_name', 'string', 'max' => 150],
            // Namen stehen in den Mails („Hallo …,“): nur Buchstaben und übliche Zeichen, keine Links oder Werbetexte
            [['kind_vorname', 'kind_nachname', 'eltern_name'], 'match', 'pattern' => '/^\p{L}[\p{L} .,\'’&\/-]*$/u',
                'message' => 'Bitte nur den Namen angeben (Buchstaben, Leerzeichen, Bindestrich).'],
            ['email', 'email'],
            ['email', 'string', 'max' => 150],
            [['telefon', 'notfall_telefon'], 'string', 'max' => 50],
            [['telefon', 'notfall_telefon'], 'match', 'pattern' => '/^[0-9 +()\/\-]{5,}$/', 'message' => 'Bitte eine Telefonnummer angeben (nur Ziffern, Leerzeichen, + / -).'],
            [['schwimmstand', 'notiz'], 'string', 'max' => 1000],
            ['kind_geburtsdatum', 'date', 'format' => 'php:Y-m-d'],
            ['kind_geburtsdatum', 'validateAlter'],
            ['mitglied', 'boolean'],
            ['mitglied', 'validateMitglied'],
            ['zahlart', 'validateZahlart', 'skipOnEmpty' => false],
            [['bedingungen_ok', 'datenschutz_ok'], 'required', 'requiredValue' => 1, 'on' => self::SCENARIO_ELTERN,
                'message' => 'Bitte bestätigen.'],
            ['webseite', 'compare', 'compareValue' => '', 'on' => self::SCENARIO_ELTERN, 'message' => 'Bitte dieses Feld leer lassen.'],
            [['kind_geburtsdatum', 'email', 'telefon', 'notfall_telefon', 'schwimmstand', 'notiz'], 'default', 'value' => null],
        ];
    }

    public function validateAlter()
    {
        if (!$this->kind_geburtsdatum || !$this->kurs) {
            return;
        }
        if ($this->kind_geburtsdatum > date('Y-m-d')) {
            $this->addError('kind_geburtsdatum', 'Das Geburtsdatum liegt in der Zukunft.');
            return;
        }
        $alter = $this->alterBeiBeginn();
        $k = $this->kurs;
        if ($this->scenario === self::SCENARIO_ELTERN && (($k->alter_min !== null && $alter < (float)$k->alter_min)
                || ($k->alter_max !== null && $alter >= (float)$k->alter_max + 1))) {
            $this->addError('kind_geburtsdatum', 'Dieser Kurs ist für Kinder ' . $k->getAlterText() . ' (zum ersten Kurstag). '
                . 'Bei Fragen wende dich bitte an ' . $k->kontakt_email . '.');
        }
    }

    public function validateMitglied()
    {
        if ($this->scenario === self::SCENARIO_ELTERN && $this->kurs && $this->kurs->nur_mitglieder && !$this->mitglied) {
            $this->addError('mitglied', 'Dieser Kurs ist nur für Vereinsmitglieder.');
        }
    }

    public function validateZahlart()
    {
        if (!$this->kurs || !$this->kurs->hatPreis()) {
            $this->zahlart = null;
            return;
        }
        $erlaubt = $this->kurs->zahlarten();
        if (count($erlaubt) === 1 && !$this->zahlart) {
            $this->zahlart = array_key_first($erlaubt);
        }
        if (!isset($erlaubt[$this->zahlart])) {
            $this->addError('zahlart', 'Bitte eine Zahlart wählen.');
        }
    }

    public function attributeLabels()
    {
        return [
            'kind_vorname' => 'Vorname des Kindes',
            'kind_nachname' => 'Nachname des Kindes',
            'kind_geburtsdatum' => 'Geburtsdatum des Kindes',
            'schwimmstand' => 'Was kann dein Kind schon?',
            'eltern_name' => 'Name der/des Erziehungsberechtigten',
            'email' => 'E-Mail',
            'telefon' => 'Telefon (mobil)',
            'notfall_telefon' => 'Telefon für Notfälle während des Kurses',
            'mitglied' => 'Das Kind ist Mitglied im Verein',
            'zahlart' => 'Zahlart',
            'notiz' => 'Interne Notiz',
        ];
    }

    public function beforeSave($insert)
    {
        if ($insert) {
            $this->created_at = date('Y-m-d H:i:s');
            if (!$this->token) {
                $this->token = Yii::$app->security->generateRandomString(40);
            }
        }
        return parent::beforeSave($insert);
    }

    public function getKurs()
    {
        return $this->hasOne(Kurs::class, ['id' => 'kurs_id']);
    }

    public function getKindName(): string
    {
        return trim($this->kind_vorname . ' ' . $this->kind_nachname);
    }

    /** Alter des Kindes am ersten Kurstag in Jahren (mit Nachkommastellen) */
    public function alterBeiBeginn(): ?float
    {
        if (!$this->kind_geburtsdatum || !$this->kurs) {
            return null;
        }
        $d = (new \DateTime($this->kind_geburtsdatum))->diff(new \DateTime($this->kurs->beginn));
        return $d->y + $d->m / 12;
    }

    public function getAlterText(): string
    {
        $a = $this->alterBeiBeginn();
        if ($a === null) {
            return '';
        }
        $j = (int)floor($a);
        $m = (int)round(($a - $j) * 12);
        return $j . ' J.' . ($m ? " $m M." : '');
    }

    /** Anmeldenummer, z. B. „K3-17“ (Kurs 3, Anmeldung 17) */
    public function getNummer(): string
    {
        return 'K' . $this->kurs_id . '-' . $this->id;
    }

    public function getVerwendungszweck(): string
    {
        return $this->getNummer() . ' ' . $this->getKindName();
    }

    /** Position auf der Warteliste (1 = als Nächstes dran) */
    public function getWartelistenPosition(): ?int
    {
        if ($this->status !== self::STATUS_WARTELISTE) {
            return null;
        }
        return 1 + (int)self::find()->where(['kurs_id' => $this->kurs_id, 'status' => self::STATUS_WARTELISTE])
                ->andWhere(['or', ['<', 'bestaetigt_am', $this->bestaetigt_am],
                    ['and', ['bestaetigt_am' => $this->bestaetigt_am], ['<', 'id', $this->id]]])->count();
    }

    public function zahlungsfrist(): ?string
    {
        if (!$this->platz_am || !$this->kurs->zahlungsfrist_tage || $this->zahlart !== self::ZAHLART_UEBERWEISUNG) {
            return null;
        }
        $frist = date('Y-m-d', strtotime($this->platz_am . ' +' . $this->kurs->zahlungsfrist_tage . ' days'));
        // nie nach dem ersten Kurstag
        return min($frist, $this->kurs->beginn);
    }

    public function getStatusText(): string
    {
        return [
            self::STATUS_UNBESTAETIGT => 'E-Mail noch nicht bestätigt',
            self::STATUS_PLATZ => 'Platz bestätigt',
            self::STATUS_WARTELISTE => 'Warteliste',
            self::STATUS_STORNIERT => 'abgemeldet',
        ][$this->status] ?? $this->status;
    }

    public function getStatusUrl(bool $scheme = true): string
    {
        return $this->kurs->elternUrl('status', ['t' => $this->token], $scheme);
    }
}
