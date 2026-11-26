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
 * @property string|null $sprache Sprache beim Anmelden (Yii::$app->language), für Mails und die Seiten aus den Mail-Links; leer = Deutsch
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
    /** Verwalter ändert eine bestehende Anmeldung (ohne Mail, Status bleibt) */
    public const SCENARIO_BEARBEITEN = 'bearbeiten';

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
            self::SCENARIO_BEARBEITEN => array_merge($felder, ['notiz', 'betrag', 'bezahlt_am']),
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
                'message' => Yii::t('KurseModule.base', 'Bitte nur den Namen angeben (Buchstaben, Leerzeichen, Bindestrich).')],
            ['email', 'email'],
            ['email', 'string', 'max' => 150],
            [['telefon', 'notfall_telefon'], 'string', 'max' => 50],
            [['telefon', 'notfall_telefon'], 'match', 'pattern' => '/^[0-9 +()\/\-]{5,}$/', 'message' => Yii::t('KurseModule.base', 'Bitte eine Telefonnummer angeben (nur Ziffern, Leerzeichen, + / -).')],
            [['schwimmstand', 'notiz'], 'string', 'max' => 1000],
            ['kind_geburtsdatum', 'date', 'format' => 'php:Y-m-d'],
            ['kind_geburtsdatum', 'validateAlter'],
            ['mitglied', 'boolean'],
            ['mitglied', 'validateMitglied'],
            ['zahlart', 'validateZahlart', 'skipOnEmpty' => false],
            [['bedingungen_ok', 'datenschutz_ok'], 'required', 'requiredValue' => 1, 'on' => self::SCENARIO_ELTERN,
                'message' => Yii::t('KurseModule.base', 'Bitte bestätigen.')],
            ['webseite', 'compare', 'compareValue' => '', 'on' => self::SCENARIO_ELTERN, 'message' => Yii::t('KurseModule.base', 'Bitte dieses Feld leer lassen.')],
            ['betrag', 'number', 'min' => 0, 'max' => 9999, 'on' => self::SCENARIO_BEARBEITEN],
            ['bezahlt_am', 'date', 'format' => 'php:Y-m-d', 'on' => self::SCENARIO_BEARBEITEN],
            [['kind_geburtsdatum', 'email', 'telefon', 'notfall_telefon', 'schwimmstand', 'notiz', 'betrag', 'bezahlt_am'], 'default', 'value' => null],
        ];
    }

    public function validateAlter()
    {
        if (!$this->kind_geburtsdatum || !$this->kurs) {
            return;
        }
        if ($this->kind_geburtsdatum > date('Y-m-d')) {
            $this->addError('kind_geburtsdatum', Yii::t('KurseModule.base', 'Das Geburtsdatum liegt in der Zukunft.'));
            return;
        }
        $alter = $this->alterBeiBeginn();
        $k = $this->kurs;
        if ($this->scenario === self::SCENARIO_ELTERN && (($k->alter_min !== null && $alter < (float)$k->alter_min)
                || ($k->alter_max !== null && $alter >= (float)$k->alter_max + 1))) {
            $this->addError('kind_geburtsdatum', Yii::t('KurseModule.base', 'Dieser Kurs ist für Kinder {alter} (zum ersten Kurstag). Bei Fragen wende dich bitte an {email}.',
                ['alter' => $k->getAlterText(), 'email' => $k->kontakt_email]));
        }
    }

    public function validateMitglied()
    {
        if ($this->scenario === self::SCENARIO_ELTERN && $this->kurs && $this->kurs->nur_mitglieder && !$this->mitglied) {
            $this->addError('mitglied', Yii::t('KurseModule.base', 'Dieser Kurs ist nur für Vereinsmitglieder.'));
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
            $this->addError('zahlart', Yii::t('KurseModule.base', 'Bitte eine Zahlart wählen.'));
        }
    }

    public function attributeLabels()
    {
        return [
            'kind_vorname' => Yii::t('KurseModule.base', 'Vorname des Kindes'),
            'kind_nachname' => Yii::t('KurseModule.base', 'Nachname des Kindes'),
            'kind_geburtsdatum' => Yii::t('KurseModule.base', 'Geburtsdatum des Kindes'),
            'schwimmstand' => Yii::t('KurseModule.base', 'Was kann dein Kind schon?'),
            'eltern_name' => Yii::t('KurseModule.base', 'Name der/des Erziehungsberechtigten'),
            'email' => Yii::t('KurseModule.base', 'E-Mail'),
            'telefon' => Yii::t('KurseModule.base', 'Telefon (mobil)'),
            'notfall_telefon' => Yii::t('KurseModule.base', 'Telefon für Notfälle während des Kurses'),
            'mitglied' => Yii::t('KurseModule.base', 'Das Kind ist Mitglied im Verein'),
            'zahlart' => Yii::t('KurseModule.base', 'Zahlart'),
            'notiz' => Yii::t('KurseModule.base', 'Interne Notiz'),
            'betrag' => Yii::t('KurseModule.base', 'Kursgebühr (€)'),
            'bezahlt_am' => Yii::t('KurseModule.base', 'Bezahlt am (leer = offen)'),
        ];
    }

    public function beforeSave($insert)
    {
        if ($insert) {
            $this->created_at = date('Y-m-d H:i:s');
            if (!$this->token) {
                $this->token = Yii::$app->security->generateRandomString(40);
            }
            // Sprache der Eltern (Formular), damit alle späteren Mails – auch aus dem Cron – in dieser Sprache kommen.
            // Von Hand eingetragene Anmeldungen: Sprache unbekannt, also Standard (Deutsch).
            if ($this->scenario === self::SCENARIO_ELTERN && $this->hasAttribute('sprache') && !$this->sprache) {
                $this->sprache = mb_substr((string)Yii::$app->language, 0, 10);
            }
        }
        return parent::beforeSave($insert);
    }

    /** Sprache für Mails an die Eltern (gespeichert beim Anmelden), sonst Deutsch */
    public function getSprache(): string
    {
        return ($this->hasAttribute('sprache') ? $this->sprache : null) ?: 'de';
    }

    /** $fn in der Sprache der Eltern ausführen (Mailtexte, Datumsformate), danach die bisherige Sprache wiederherstellen */
    public function inSprache(callable $fn)
    {
        $alt = [Yii::$app->language, Yii::$app->formatter->locale];
        Yii::$app->language = Yii::$app->formatter->locale = $this->getSprache();
        try {
            return $fn();
        } finally {
            [Yii::$app->language, Yii::$app->formatter->locale] = $alt;
        }
    }

    /**
     * Seiten aus den Mail-Links (Bestätigen, Status): Gäste ohne selbst gewählte Sprache (Cookie „language“) sehen sie
     * in der Sprache, in der sie sich angemeldet haben. Angemeldete Nutzer behalten ihre Kontosprache.
     */
    public function spracheFuerSeite(): void
    {
        $request = Yii::$app->request;
        if (!$this->hasAttribute('sprache') || !$this->sprache || !Yii::$app->user->isGuest
            || !$request instanceof \yii\web\Request || isset($request->cookies['language'])) {
            return;
        }
        Yii::$app->language = Yii::$app->formatter->locale = $this->sprache;
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
        return $m ? Yii::t('KurseModule.base', '{jahre} J. {monate} M.', ['jahre' => $j, 'monate' => $m])
            : Yii::t('KurseModule.base', '{jahre} J.', ['jahre' => $j]);
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
        return $this->kurs->beginn_offen ? $frist : min($frist, $this->kurs->beginn);
    }

    public function getStatusText(): string
    {
        return [
            self::STATUS_UNBESTAETIGT => Yii::t('KurseModule.base', 'E-Mail noch nicht bestätigt'),
            self::STATUS_PLATZ => Yii::t('KurseModule.base', 'Platz bestätigt'),
            self::STATUS_WARTELISTE => Yii::t('KurseModule.base', 'Warteliste'),
            self::STATUS_STORNIERT => Yii::t('KurseModule.base', 'abgemeldet'),
        ][$this->status] ?? $this->status;
    }

    public function getStatusUrl(bool $scheme = true): string
    {
        return $this->kurs->elternUrl('status', ['t' => $this->token], $scheme);
    }
}
