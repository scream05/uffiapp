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

    /** PRO_PERSON übersetzt für Anzeige und Formular (PRO_PERSON selbst bleibt deutsch, z. B. für den KI-Assistenten) */
    public static function proPersonLabels(): array
    {
        return array_map(fn($label) => Yii::t('GewerbeModule.base', $label), self::PRO_PERSON);
    }

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
            [['gueltig_ab', 'gueltig_bis'], 'date', 'format' => 'php:Y-m-d', 'message' => Yii::t('GewerbeModule.base', 'Bitte ein Datum wählen.')],
            ['gueltig_bis', 'validateZeitraum'],
            ['pro_person', 'in', 'range' => array_keys(self::PRO_PERSON)],
            ['limit_gesamt', 'default', 'value' => null],
            ['limit_gesamt', 'integer', 'min' => 1, 'max' => 100000, 'message' => Yii::t('GewerbeModule.base', 'Bitte eine Zahl ab 1 angeben oder das Feld leer lassen.')],
            ['nur_geoeffnet', 'boolean'],
            ['regelnOk', 'required', 'requiredValue' => 1, 'on' => 'formular',
                'message' => Yii::t('GewerbeModule.base', 'Bitte bestätige die Hinweise zu erlaubten Rabatten.')],
        ];
    }

    public function validateZeitraum()
    {
        if ($this->hasErrors('gueltig_ab') || $this->hasErrors('gueltig_bis')) {
            return;
        }
        if ($this->gueltig_bis < $this->gueltig_ab) {
            $this->addError('gueltig_bis', Yii::t('GewerbeModule.base', 'Das Ende muss nach dem Anfang liegen.'));
        } elseif ($this->gueltig_bis < date('Y-m-d')) {
            $this->addError('gueltig_bis', Yii::t('GewerbeModule.base', 'Das Ende liegt in der Vergangenheit.'));
        } elseif ((strtotime($this->gueltig_bis) - strtotime($this->gueltig_ab)) / 86400 > self::MAX_TAGE) {
            $this->addError('gueltig_bis', Yii::t('GewerbeModule.base', 'Ein Gutschein läuft höchstens ein Jahr. Danach kannst du einen neuen anlegen.'));
        }
    }

    public function attributeLabels()
    {
        return [
            'titel' => Yii::t('GewerbeModule.base', 'Was gibt es?'),
            'bedingungen' => Yii::t('GewerbeModule.base', 'Bedingungen (freiwillig)'),
            'gueltig_ab' => Yii::t('GewerbeModule.base', 'Gültig ab'),
            'gueltig_bis' => Yii::t('GewerbeModule.base', 'Gültig bis (einschließlich)'),
            'pro_person' => Yii::t('GewerbeModule.base', 'Wie oft pro Person?'),
            'limit_gesamt' => Yii::t('GewerbeModule.base', 'Höchstens insgesamt (freiwillig)'),
            'nur_geoeffnet' => Yii::t('GewerbeModule.base', 'Nur während der Öffnungszeiten einlösbar'),
            'regelnOk' => Yii::t('GewerbeModule.base', 'Ich habe die Hinweise gelesen. Der Gutschein ist ein Rabatt meines Betriebs.'),
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
        $de = str_starts_with((string)Yii::$app->language, 'de');
        if ($this->status === self::STATUS_BEENDET || $this->istAbgelaufen()) {
            return Yii::t('GewerbeModule.base', 'Beendet');
        }
        if ($this->status === self::STATUS_PAUSIERT) {
            return Yii::t('GewerbeModule.base', 'Gerade pausiert');
        }
        if ($this->istAufgebraucht()) {
            return Yii::t('GewerbeModule.base', 'Alle eingelöst');
        }
        $teile = [];
        if ($this->gueltig_ab > date('Y-m-d')) {
            $teile[] = Yii::t('GewerbeModule.base', 'Ab {datum}', ['datum' => $f->asDate($this->gueltig_ab, $de ? 'php:j.n.' : 'medium')]);
        }
        $teile[] = Yii::t('GewerbeModule.base', 'bis {datum}', ['datum' => $f->asDate($this->gueltig_bis, $de ? 'php:j.n.Y' : 'medium')]);
        if (($rest = $this->restMenge()) !== null && $rest <= 20) {
            $teile[] = Yii::t('GewerbeModule.base', 'noch {anzahl}', ['anzahl' => $rest]);
        }
        $text = implode(' · ', $teile);
        return mb_strtoupper(mb_substr($text, 0, 1)) . mb_substr($text, 1);
    }

    public function getUrl(bool $scheme = false): string
    {
        $space = Yii::$app->getModule('gewerbe')->getDirectorySpace();
        return $space ? $space->createUrl('/gewerbe/gutschein/view', ['id' => $this->id], $scheme) : '/';
    }
}
