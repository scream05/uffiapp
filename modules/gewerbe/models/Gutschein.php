<?php

namespace humhub\modules\gewerbe\models;

use humhub\components\ActiveRecord;
use humhub\modules\user\models\User;
use Yii;

/**
 * Rabatt-Gutschein eines Betriebs (kein Geldwert). Bestätigte Verwalter legen ihn an, er ist sofort sichtbar.
 * Eingelöst wird im Laden über den Theken-QR des Betriebs (siehe GutscheinDienst).
 *
 * @property int $id
 * @property int $betrieb_id
 * @property string $titel
 * @property string|null $bedingungen
 * @property string $gueltig_ab Y-m-d
 * @property string $gueltig_bis Y-m-d (einschließlich)
 * @property string $pro_person einmal | tag | woche | monat | unbegrenzt
 * @property int|null $limit_gesamt
 * @property int $nur_geoeffnet
 * @property string $status aktiv | pausiert | beendet
 * @property string|null $pause_grund
 * @property int $eingeloest
 * @property int|null $created_by
 * @property string $created_at
 * @property string|null $updated_at
 * @property-read Betrieb $betrieb
 * @property-read User|null $ersteller
 */
class Gutschein extends ActiveRecord
{
    public const STATUS_AKTIV = 'aktiv';
    public const STATUS_PAUSIERT = 'pausiert';
    public const STATUS_BEENDET = 'beendet';

    public const PRO_PERSON = [
        'einmal' => 'einmal',
        'tag' => 'einmal am Tag',
        'woche' => 'einmal in 7 Tagen',
        'monat' => 'einmal in 30 Tagen',
        'unbegrenzt' => 'beliebig oft',
    ];

    /** Höchstlaufzeit, damit keine Dauer-Gutscheine vergessen werden */
    public const MAX_TAGE = 366;

    /** Formular: Hinweise zu erlaubten Rabatten gelesen */
    public $regelnOk = false;

    public static function tableName()
    {
        return 'gewerbe_gutschein';
    }

    public function rules()
    {
        return [
            [['titel', 'bedingungen'], 'trim'],
            [['titel', 'gueltig_ab', 'gueltig_bis', 'pro_person'], 'required'],
            ['titel', 'string', 'min' => 4, 'max' => 120],
            ['bedingungen', 'string', 'max' => 1000],
            ['bedingungen', 'default', 'value' => null],
            [['gueltig_ab', 'gueltig_bis'], 'date', 'format' => 'php:Y-m-d', 'message' => 'Bitte ein Datum wählen.'],
            ['gueltig_bis', 'validateZeitraum'],
            ['pro_person', 'in', 'range' => array_keys(self::PRO_PERSON)],
            ['limit_gesamt', 'default', 'value' => null],
            ['limit_gesamt', 'integer', 'min' => 1, 'max' => 100000, 'message' => 'Bitte eine Zahl ab 1 angeben oder das Feld leer lassen.'],
            ['nur_geoeffnet', 'boolean'],
            ['regelnOk', 'required', 'requiredValue' => 1, 'on' => 'formular',
                'message' => 'Bitte bestätige die Hinweise zu erlaubten Rabatten.'],
        ];
    }

    public function validateZeitraum()
    {
        if ($this->hasErrors('gueltig_ab') || $this->hasErrors('gueltig_bis')) {
            return;
        }
        if ($this->gueltig_bis < $this->gueltig_ab) {
            $this->addError('gueltig_bis', 'Das Ende muss nach dem Anfang liegen.');
        } elseif ($this->gueltig_bis < date('Y-m-d')) {
            $this->addError('gueltig_bis', 'Das Ende liegt in der Vergangenheit.');
        } elseif ((strtotime($this->gueltig_bis) - strtotime($this->gueltig_ab)) / 86400 > self::MAX_TAGE) {
            $this->addError('gueltig_bis', 'Ein Gutschein läuft höchstens ein Jahr. Danach kannst du einen neuen anlegen.');
        }
    }

    public function attributeLabels()
    {
        return [
            'titel' => 'Was gibt es?',
            'bedingungen' => 'Bedingungen (freiwillig)',
            'gueltig_ab' => 'Gültig ab',
            'gueltig_bis' => 'Gültig bis (einschließlich)',
            'pro_person' => 'Wie oft pro Person?',
            'limit_gesamt' => 'Höchstens insgesamt (freiwillig)',
            'nur_geoeffnet' => 'Nur während der Öffnungszeiten einlösbar',
            'regelnOk' => 'Ich habe die Hinweise gelesen. Der Gutschein ist ein Rabatt meines Betriebs.',
        ];
    }

    public function beforeSave($insert)
    {
        $now = date('Y-m-d H:i:s');
        if ($insert) {
            $this->created_at = $now;
            $this->created_by ??= Yii::$app->has('user') && !Yii::$app->user->isGuest ? Yii::$app->user->id : null;
        } else {
            $this->updated_at = $now;
        }
        return parent::beforeSave($insert);
    }

    public function getBetrieb()
    {
        return $this->hasOne(Betrieb::class, ['id' => 'betrieb_id']);
    }

    public function getErsteller()
    {
        return $this->hasOne(User::class, ['id' => 'created_by']);
    }

    /** Sichtbar für alle: aktiv oder pausiert, Zeitraum noch nicht vorbei */
    public static function sichtbar(): \yii\db\ActiveQuery
    {
        return static::find()->where(['gewerbe_gutschein.status' => [self::STATUS_AKTIV, self::STATUS_PAUSIERT]])
            ->andWhere(['>=', 'gewerbe_gutschein.gueltig_bis', date('Y-m-d')]);
    }

    public function istAbgelaufen(): bool
    {
        return $this->gueltig_bis < date('Y-m-d');
    }

    public function istAufgebraucht(): bool
    {
        return $this->limit_gesamt !== null && $this->eingeloest >= $this->limit_gesamt;
    }

    public function restMenge(): ?int
    {
        return $this->limit_gesamt === null ? null : max(0, $this->limit_gesamt - $this->eingeloest);
    }

    /** Kurzer Stand für Karten und Listen: „Gültig bis 31.12.“, „Noch 12 Stück“, „Pausiert“ … */
    public function getStandText(): string
    {
        $f = Yii::$app->formatter;
        if ($this->status === self::STATUS_BEENDET || $this->istAbgelaufen()) {
            return 'Beendet';
        }
        if ($this->status === self::STATUS_PAUSIERT) {
            return 'Gerade pausiert';
        }
        if ($this->istAufgebraucht()) {
            return 'Alle eingelöst';
        }
        $teile = [];
        if ($this->gueltig_ab > date('Y-m-d')) {
            $teile[] = 'Ab ' . $f->asDate($this->gueltig_ab, 'php:j.n.');
        }
        $teile[] = 'bis ' . $f->asDate($this->gueltig_bis, 'php:j.n.Y');
        if (($rest = $this->restMenge()) !== null && $rest <= 20) {
            $teile[] = "noch $rest";
        }
        return ucfirst(implode(' · ', $teile));
    }

    public function getUrl(bool $scheme = false): string
    {
        $space = Yii::$app->getModule('gewerbe')->getDirectorySpace();
        return $space ? $space->createUrl('/gewerbe/gutschein/view', ['id' => $this->id], $scheme) : '/';
    }
}
