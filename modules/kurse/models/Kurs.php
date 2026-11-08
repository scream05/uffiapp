<?php

namespace humhub\modules\kurse\models;

use humhub\components\ActiveRecord;
use humhub\modules\vereine\models\Verein;
use Yii;
use yii\helpers\Url;

/**
 * Ein Kurs eines Vereins (z. B. Seepferdchen-Kurs).
 *
 * @property int $id
 * @property int $verein_id
 * @property string $status entwurf | offen | abgesagt
 * @property string $titel
 * @property string|null $niveau
 * @property string|null $beschreibung
 * @property string $ort
 * @property string $beginn
 * @property string|null $ende
 * @property string|null $zeit_von
 * @property string|null $zeit_bis
 * @property string|null $termine_hinweis
 * @property int|null $einheiten
 * @property string|null $alter_min
 * @property string|null $alter_max
 * @property int $plaetze
 * @property int|null $warteliste_max
 * @property string|null $preis_mitglied
 * @property string|null $preis_nichtmitglied
 * @property bool $nur_mitglieder
 * @property bool $zahlart_bar
 * @property bool $zahlart_ueberweisung
 * @property string|null $kontoinhaber
 * @property string|null $iban
 * @property int|null $zahlungsfrist_tage
 * @property string|null $anmeldung_ab
 * @property string|null $anmeldung_bis
 * @property int|null $erinnerung_tage
 * @property string $kontakt_email
 * @property string|null $bedingungen
 * @property string|null $absage_grund
 * @property string|null $erinnerung_am
 * @property string $created_at
 * @property-read Verein $verein
 * @property-read Anmeldung[] $anmeldungen
 */
class Kurs extends ActiveRecord
{
    public const STATUS_ENTWURF = 'entwurf';
    public const STATUS_OFFEN = 'offen';
    public const STATUS_ABGESAGT = 'abgesagt';

    public static function tableName()
    {
        return 'kurs';
    }

    public function rules()
    {
        return [
            [['titel', 'ort', 'beginn', 'plaetze', 'kontakt_email'], 'required'],
            [['titel'], 'string', 'max' => 150],
            [['niveau', 'kontoinhaber'], 'string', 'max' => 100],
            [['ort', 'termine_hinweis'], 'string', 'max' => 255],
            [['beschreibung', 'bedingungen'], 'string', 'max' => 10000],
            [['titel', 'niveau', 'ort', 'termine_hinweis', 'kontoinhaber', 'iban', 'kontakt_email', 'beschreibung', 'bedingungen'], 'trim'],
            [['beginn', 'ende'], 'date', 'format' => 'php:Y-m-d'],
            ['ende', 'compare', 'compareAttribute' => 'beginn', 'operator' => '>=', 'type' => 'date', 'skipOnEmpty' => true,
                'message' => 'Das Ende darf nicht vor dem Beginn liegen.'],
            [['zeit_von', 'zeit_bis'], 'match', 'pattern' => '/^\d{2}:\d{2}(:\d{2})?$/'],
            [['plaetze'], 'integer', 'min' => 1, 'max' => 500],
            [['warteliste_max'], 'integer', 'min' => 0, 'max' => 500],
            [['einheiten'], 'integer', 'min' => 1, 'max' => 200],
            [['zahlungsfrist_tage'], 'integer', 'min' => 1, 'max' => 90],
            [['erinnerung_tage'], 'integer', 'min' => 1, 'max' => 30],
            [['alter_min', 'alter_max'], 'number', 'min' => 0, 'max' => 99],
            ['alter_max', 'compare', 'compareAttribute' => 'alter_min', 'operator' => '>=', 'type' => 'number', 'skipOnEmpty' => true,
                'message' => 'Das Höchstalter muss mindestens so groß wie das Mindestalter sein.'],
            [['preis_mitglied', 'preis_nichtmitglied'], 'number', 'min' => 0, 'max' => 9999],
            [['nur_mitglieder', 'zahlart_bar', 'zahlart_ueberweisung'], 'boolean'],
            ['zahlart_bar', 'validateZahlart'],
            ['kontakt_email', 'email'],
            ['iban', 'filter', 'filter' => fn($v) => $v === null || $v === '' ? null : strtoupper(preg_replace('/\s+/', '', $v))],
            ['iban', 'validateIban'],
            [['anmeldung_ab', 'anmeldung_bis'], 'filter', 'filter' => fn($v) => $v ? str_replace('T', ' ', substr($v, 0, 16)) . ':00' : null],
            [['anmeldung_ab', 'anmeldung_bis'], 'date', 'format' => 'php:Y-m-d H:i:s'],
            [['ende', 'zeit_von', 'zeit_bis', 'einheiten', 'alter_min', 'alter_max', 'warteliste_max', 'preis_mitglied',
                'preis_nichtmitglied', 'zahlungsfrist_tage', 'erinnerung_tage', 'niveau', 'termine_hinweis', 'kontoinhaber',
                'beschreibung', 'bedingungen'], 'default', 'value' => null],
        ];
    }

    public function validateZahlart()
    {
        if (!$this->zahlart_bar && !$this->zahlart_ueberweisung) {
            $this->addError('zahlart_bar', 'Bitte mindestens eine Zahlart wählen.');
        }
        if ($this->zahlart_ueberweisung && $this->hatPreis() && (!$this->iban || !$this->kontoinhaber)) {
            $this->addError('iban', 'Für Überweisung bitte Kontoinhaber und IBAN angeben.');
        }
    }

    public function validateIban()
    {
        if (!$this->iban) {
            return;
        }
        $iban = $this->iban;
        if (!preg_match('/^[A-Z]{2}\d{2}[A-Z0-9]{10,30}$/', $iban)) {
            $this->addError('iban', 'Die IBAN sieht nicht gültig aus.');
            return;
        }
        $num = '';
        foreach (str_split(substr($iban, 4) . substr($iban, 0, 4)) as $c) {
            $num .= ctype_alpha($c) ? (string)(ord($c) - 55) : $c;
        }
        $rest = 0;
        foreach (str_split($num, 7) as $teil) {
            $rest = (int)(($rest . $teil) % 97);
        }
        if ($rest !== 1) {
            $this->addError('iban', 'Die Prüfziffer der IBAN stimmt nicht. Bitte noch einmal kontrollieren.');
        }
    }

    public function attributeLabels()
    {
        return [
            'titel' => 'Kursname',
            'niveau' => 'Kursziel / Niveau',
            'beschreibung' => 'Beschreibung',
            'ort' => 'Ort',
            'beginn' => 'Erster Kurstag',
            'ende' => 'Letzter Kurstag',
            'zeit_von' => 'Uhrzeit von',
            'zeit_bis' => 'bis',
            'termine_hinweis' => 'Hinweis zu den Terminen',
            'einheiten' => 'Anzahl der Einheiten',
            'alter_min' => 'Mindestalter (Jahre)',
            'alter_max' => 'Höchstalter (Jahre)',
            'plaetze' => 'Plätze',
            'warteliste_max' => 'Plätze auf der Warteliste',
            'preis_mitglied' => 'Preis für Mitglieder (€)',
            'preis_nichtmitglied' => 'Preis für Nichtmitglieder (€)',
            'nur_mitglieder' => 'Nur für Vereinsmitglieder',
            'zahlart_bar' => 'Barzahlung am ersten Kurstag',
            'zahlart_ueberweisung' => 'Überweisung',
            'kontoinhaber' => 'Kontoinhaber',
            'iban' => 'IBAN',
            'zahlungsfrist_tage' => 'Zahlungsfrist (Tage nach Zusage)',
            'anmeldung_ab' => 'Anmeldung ab',
            'anmeldung_bis' => 'Anmeldung bis',
            'erinnerung_tage' => 'Erinnerung so viele Tage vor Beginn',
            'kontakt_email' => 'Kontakt-E-Mail (Antworten auf Mails)',
            'bedingungen' => 'Teilnahmebedingungen',
        ];
    }

    public function beforeSave($insert)
    {
        $now = date('Y-m-d H:i:s');
        if ($insert) {
            $this->created_at = $now;
            $this->created_by = Yii::$app->user->isGuest ? null : Yii::$app->user->id;
        } else {
            $this->updated_at = $now;
            $this->updated_by = Yii::$app->user->isGuest ? null : Yii::$app->user->id;
        }
        return parent::beforeSave($insert);
    }

    public function getVerein()
    {
        return $this->hasOne(Verein::class, ['id' => 'verein_id']);
    }

    public function getAnmeldungen()
    {
        return $this->hasMany(Anmeldung::class, ['kurs_id' => 'id']);
    }

    public function zaehle(string $status): int
    {
        return (int)Anmeldung::find()->where(['kurs_id' => $this->id, 'status' => $status])->count();
    }

    public function freiePlaetze(): int
    {
        return max(0, $this->plaetze - $this->zaehle(Anmeldung::STATUS_PLATZ));
    }

    /** Ist auf der Warteliste noch Platz? (null = unbegrenzt) */
    public function wartelisteFrei(): bool
    {
        return $this->warteliste_max === null || $this->zaehle(Anmeldung::STATUS_WARTELISTE) < $this->warteliste_max;
    }

    /** Letzter Kurstag (angegeben oder aus Beginn + Einheiten geschätzt, wöchentlich) */
    public function getEndeDatum(): string
    {
        if ($this->ende) {
            return $this->ende;
        }
        $wochen = max(0, (int)$this->einheiten - 1);
        return date('Y-m-d', strtotime($this->beginn . " +$wochen weeks"));
    }

    public function istVorbei(): bool
    {
        return $this->getEndeDatum() < date('Y-m-d');
    }

    public function hatBegonnen(): bool
    {
        return $this->beginn <= date('Y-m-d');
    }

    /** Anmeldephase: null = offen, sonst Grund als Text */
    public function anmeldungGesperrt(): ?string
    {
        if ($this->status === self::STATUS_ABGESAGT) {
            return 'Dieser Kurs wurde abgesagt.';
        }
        if ($this->status !== self::STATUS_OFFEN) {
            return 'Für diesen Kurs ist die Anmeldung noch nicht geöffnet.';
        }
        $jetzt = date('Y-m-d H:i:s');
        if ($this->anmeldung_ab && $jetzt < $this->anmeldung_ab) {
            return 'Die Anmeldung startet am ' . Yii::$app->formatter->asDatetime($this->anmeldung_ab, 'php:d.m.Y \u\m H:i') . ' Uhr.';
        }
        if ($this->anmeldung_bis && $jetzt > $this->anmeldung_bis) {
            return 'Die Anmeldefrist ist abgelaufen.';
        }
        if ($this->hatBegonnen()) {
            return 'Der Kurs hat schon begonnen.';
        }
        if ($this->freiePlaetze() === 0 && !$this->wartelisteFrei()) {
            return 'Der Kurs und die Warteliste sind voll.';
        }
        return null;
    }

    public function hatPreis(): bool
    {
        return (float)$this->preis_mitglied > 0 || (float)$this->preis_nichtmitglied > 0;
    }

    public function preisFuer(bool $mitglied): ?float
    {
        $p = $mitglied ? ($this->preis_mitglied ?? $this->preis_nichtmitglied) : ($this->preis_nichtmitglied ?? $this->preis_mitglied);
        return $p === null ? null : (float)$p;
    }

    /** @return array<string,string> erlaubte Zahlarten */
    public function zahlarten(): array
    {
        $z = [];
        if ($this->zahlart_ueberweisung) {
            $z[Anmeldung::ZAHLART_UEBERWEISUNG] = 'Überweisung';
        }
        if ($this->zahlart_bar) {
            $z[Anmeldung::ZAHLART_BAR] = 'Bar am ersten Kurstag';
        }
        return $z;
    }

    public static function euro($betrag): string
    {
        return number_format((float)$betrag, 2, ',', '.') . ' €';
    }

    public function getPreisText(): string
    {
        if (!$this->hatPreis()) {
            return 'kostenlos';
        }
        $m = $this->preis_mitglied;
        $n = $this->preis_nichtmitglied;
        if ($this->nur_mitglieder || $n === null || (float)$m === (float)$n) {
            return self::euro($m ?? $n);
        }
        if ($m === null) {
            return self::euro($n);
        }
        return self::euro($m) . ' für Mitglieder, ' . self::euro($n) . ' für Nichtmitglieder';
    }

    public function getAlterText(): ?string
    {
        $f = fn($v) => rtrim(rtrim(number_format((float)$v, 1, ',', ''), '0'), ',');
        if ($this->alter_min !== null && $this->alter_max !== null) {
            return $f($this->alter_min) . ' bis ' . $f($this->alter_max) . ' Jahre';
        }
        if ($this->alter_min !== null) {
            return 'ab ' . $f($this->alter_min) . ' Jahren';
        }
        if ($this->alter_max !== null) {
            return 'bis ' . $f($this->alter_max) . ' Jahre';
        }
        return null;
    }

    private static function wochentag(string $datum): string
    {
        return ['Sonntag', 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag'][(int)date('w', strtotime($datum))];
    }

    public function getZeitText(): ?string
    {
        if (!$this->zeit_von) {
            return null;
        }
        return substr($this->zeit_von, 0, 5) . ($this->zeit_bis ? '–' . substr($this->zeit_bis, 0, 5) : '') . ' Uhr';
    }

    /** z. B. „ab Dienstag, 13.01.2027 · 15:00–15:45 Uhr · 10 Einheiten“ */
    public function getTermineText(): string
    {
        $teile = [self::wochentag($this->beginn) . ', ' . date('d.m.Y', strtotime($this->beginn))
            . ($this->ende ? ' bis ' . self::wochentag($this->ende) . ', ' . date('d.m.Y', strtotime($this->ende)) : '')];
        if ($z = $this->getZeitText()) {
            $teile[] = $z;
        }
        if ($this->einheiten) {
            $teile[] = $this->einheiten . ($this->einheiten == 1 ? ' Einheit' : ' Einheiten');
        }
        return implode(' · ', $teile);
    }

    public function getStatusLabel(): array
    {
        if ($this->status === self::STATUS_ENTWURF) {
            return ['Entwurf (nicht sichtbar)', 'default'];
        }
        if ($this->status === self::STATUS_ABGESAGT) {
            return ['abgesagt', 'danger'];
        }
        if ($this->istVorbei()) {
            return ['beendet', 'default'];
        }
        if ($this->freiePlaetze() > 0) {
            return [$this->freiePlaetze() . ' von ' . $this->plaetze . ' Plätzen frei', 'success'];
        }
        return $this->wartelisteFrei() ? ['ausgebucht – Warteliste', 'warning'] : ['ausgebucht', 'danger'];
    }

    public function getUrl(bool $scheme = false): string
    {
        $space = Yii::$app->getModule('kurse')->getSpace();
        return $space ? $space->createUrl('/kurse/kurs/view', ['id' => $this->id], $scheme) : Url::home($scheme);
    }

    public function createUrl(string $route, array $params = [], bool $scheme = false): string
    {
        $space = Yii::$app->getModule('kurse')->getSpace();
        return $space ? $space->createUrl($route, $params, $scheme) : Url::home($scheme);
    }
}
