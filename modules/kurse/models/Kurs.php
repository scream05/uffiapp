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
                'message' => Yii::t('KurseModule.base', 'Das Ende darf nicht vor dem Beginn liegen.')],
            [['zeit_von', 'zeit_bis'], 'match', 'pattern' => '/^\d{2}:\d{2}(:\d{2})?$/'],
            [['plaetze'], 'integer', 'min' => 1, 'max' => 500],
            [['warteliste_max'], 'integer', 'min' => 0, 'max' => 500],
            [['einheiten'], 'integer', 'min' => 1, 'max' => 200],
            [['zahlungsfrist_tage'], 'integer', 'min' => 1, 'max' => 90],
            [['erinnerung_tage'], 'integer', 'min' => 1, 'max' => 30],
            [['alter_min', 'alter_max'], 'number', 'min' => 0, 'max' => 99],
            ['alter_max', 'compare', 'compareAttribute' => 'alter_min', 'operator' => '>=', 'type' => 'number', 'skipOnEmpty' => true,
                'message' => Yii::t('KurseModule.base', 'Das Höchstalter muss mindestens so groß wie das Mindestalter sein.')],
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
            $this->addError('zahlart_bar', Yii::t('KurseModule.base', 'Bitte mindestens eine Zahlart wählen.'));
        }
        if ($this->zahlart_ueberweisung && $this->hatPreis() && (!$this->iban || !$this->kontoinhaber)) {
            $this->addError('iban', Yii::t('KurseModule.base', 'Für Überweisung bitte Kontoinhaber und IBAN angeben.'));
        }
    }

    public function validateIban()
    {
        if (!$this->iban) {
            return;
        }
        $iban = $this->iban;
        if (!preg_match('/^[A-Z]{2}\d{2}[A-Z0-9]{10,30}$/', $iban)) {
            $this->addError('iban', Yii::t('KurseModule.base', 'Die IBAN sieht nicht gültig aus.'));
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
            $this->addError('iban', Yii::t('KurseModule.base', 'Die Prüfziffer der IBAN stimmt nicht. Bitte noch einmal kontrollieren.'));
        }
    }

    public function attributeLabels()
    {
        return [
            'titel' => Yii::t('KurseModule.base', 'Kursname'),
            'niveau' => Yii::t('KurseModule.base', 'Kursziel / Niveau'),
            'beschreibung' => Yii::t('KurseModule.base', 'Beschreibung'),
            'ort' => Yii::t('KurseModule.base', 'Ort'),
            'beginn' => Yii::t('KurseModule.base', 'Erster Kurstag'),
            'ende' => Yii::t('KurseModule.base', 'Letzter Kurstag'),
            'zeit_von' => Yii::t('KurseModule.base', 'Uhrzeit von'),
            'zeit_bis' => Yii::t('KurseModule.base', 'bis'),
            'termine_hinweis' => Yii::t('KurseModule.base', 'Hinweis zu den Terminen'),
            'einheiten' => Yii::t('KurseModule.base', 'Anzahl der Einheiten'),
            'alter_min' => Yii::t('KurseModule.base', 'Mindestalter (Jahre)'),
            'alter_max' => Yii::t('KurseModule.base', 'Höchstalter (Jahre)'),
            'plaetze' => Yii::t('KurseModule.base', 'Plätze'),
            'warteliste_max' => Yii::t('KurseModule.base', 'Plätze auf der Warteliste'),
            'preis_mitglied' => Yii::t('KurseModule.base', 'Preis für Mitglieder (€)'),
            'preis_nichtmitglied' => Yii::t('KurseModule.base', 'Preis für Nichtmitglieder (€)'),
            'nur_mitglieder' => Yii::t('KurseModule.base', 'Nur für Vereinsmitglieder'),
            'zahlart_bar' => Yii::t('KurseModule.base', 'Barzahlung am ersten Kurstag'),
            'zahlart_ueberweisung' => Yii::t('KurseModule.base', 'Überweisung'),
            'kontoinhaber' => Yii::t('KurseModule.base', 'Kontoinhaber'),
            'iban' => Yii::t('KurseModule.base', 'IBAN'),
            'zahlungsfrist_tage' => Yii::t('KurseModule.base', 'Zahlungsfrist (Tage nach Zusage)'),
            'anmeldung_ab' => Yii::t('KurseModule.base', 'Anmeldung ab'),
            'anmeldung_bis' => Yii::t('KurseModule.base', 'Anmeldung bis'),
            'erinnerung_tage' => Yii::t('KurseModule.base', 'Erinnerung so viele Tage vor Beginn'),
            'kontakt_email' => Yii::t('KurseModule.base', 'Kontakt-E-Mail (Antworten auf Mails)'),
            'bedingungen' => Yii::t('KurseModule.base', 'Teilnahmebedingungen'),
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
            return Yii::t('KurseModule.base', 'Dieser Kurs wurde abgesagt.');
        }
        if ($this->status !== self::STATUS_OFFEN) {
            return Yii::t('KurseModule.base', 'Für diesen Kurs ist die Anmeldung noch nicht geöffnet.');
        }
        $jetzt = date('Y-m-d H:i:s');
        if ($this->anmeldung_ab && $jetzt < $this->anmeldung_ab) {
            return Yii::t('KurseModule.base', 'Die Anmeldung startet am {datum} um {zeit} Uhr.',
                ['datum' => self::datum($this->anmeldung_ab), 'zeit' => substr($this->anmeldung_ab, 11, 5)]);
        }
        if ($this->anmeldung_bis && $jetzt > $this->anmeldung_bis) {
            return Yii::t('KurseModule.base', 'Die Anmeldefrist ist abgelaufen.');
        }
        if ($this->hatBegonnen()) {
            return Yii::t('KurseModule.base', 'Der Kurs hat schon begonnen.');
        }
        if ($this->freiePlaetze() === 0 && !$this->wartelisteFrei()) {
            return Yii::t('KurseModule.base', 'Der Kurs und die Warteliste sind voll.');
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

    /** Gibt es nur eine Zahlart, ist sie im Anmeldeformular schon gewählt */
    public function vorbelegteZahlart(): ?string
    {
        $z = $this->hatPreis() ? $this->zahlarten() : [];
        return count($z) === 1 ? array_key_first($z) : null;
    }

    /** @return array<string,string> erlaubte Zahlarten */
    public function zahlarten(): array
    {
        $z = [];
        if ($this->zahlart_ueberweisung) {
            $z[Anmeldung::ZAHLART_UEBERWEISUNG] = Yii::t('KurseModule.base', 'Überweisung');
        }
        if ($this->zahlart_bar) {
            $z[Anmeldung::ZAHLART_BAR] = Yii::t('KurseModule.base', 'Bar am ersten Kurstag');
        }
        return $z;
    }

    /** Betrag in Euro im Format der aktuellen Sprache (z. B. „60,00 €“, englisch „€60.00“) */
    public static function euro($betrag): string
    {
        return Yii::$app->formatter->asCurrency((float)$betrag, 'EUR');
    }

    /** Datum (Y-m-d, auch Y-m-d H:i:s – dann nur der Tag) im Format der aktuellen Sprache, deutsch „13.01.2027“ */
    public static function datum(string $datum): string
    {
        try {
            return Yii::$app->formatter->asDate(substr($datum, 0, 10), 'medium');
        } catch (\Throwable $e) {
            return $datum; // kaputter Wert: lieber roh anzeigen als die Seite abbrechen
        }
    }

    /** Zeitpunkt (Y-m-d H:i:s, Ortszeit wie gespeichert) als „13.01.2027, 18:00 Uhr“ */
    public static function datumZeit(string $zeitpunkt): string
    {
        return Yii::t('KurseModule.base', '{datum}, {zeit} Uhr', ['datum' => self::datum($zeitpunkt), 'zeit' => substr($zeitpunkt, 11, 5)]);
    }

    public function getPreisText(): string
    {
        if (!$this->hatPreis()) {
            return Yii::t('KurseModule.base', 'kostenlos');
        }
        $m = $this->preis_mitglied;
        $n = $this->preis_nichtmitglied;
        if ($this->nur_mitglieder || $n === null || (float)$m === (float)$n) {
            return self::euro($m ?? $n);
        }
        if ($m === null) {
            return self::euro($n);
        }
        return Yii::t('KurseModule.base', '{mitglieder} für Mitglieder, {andere} für Nichtmitglieder', ['mitglieder' => self::euro($m), 'andere' => self::euro($n)]);
    }

    public function getAlterText(): ?string
    {
        // ganze Jahre ohne, halbe mit einer Nachkommastelle (5 / 5,5)
        $f = fn($v) => Yii::$app->formatter->asDecimal((float)$v, fmod((float)$v, 1.0) == 0 ? 0 : 1);
        if ($this->alter_min !== null && $this->alter_max !== null) {
            return Yii::t('KurseModule.base', '{min} bis {max} Jahre', ['min' => $f($this->alter_min), 'max' => $f($this->alter_max)]);
        }
        if ($this->alter_min !== null) {
            return Yii::t('KurseModule.base', 'ab {min} Jahren', ['min' => $f($this->alter_min)]);
        }
        if ($this->alter_max !== null) {
            return Yii::t('KurseModule.base', 'bis {max} Jahre', ['max' => $f($this->alter_max)]);
        }
        return null;
    }

    /** „Dienstag, 13.01.2027“ – Wochentag und Datum im Format der aktuellen Sprache */
    private static function tagUndDatum(string $datum): string
    {
        return Yii::t('KurseModule.base', '{wochentag}, {datum}', [
            'wochentag' => Yii::$app->formatter->asDate($datum, 'EEEE'), 'datum' => self::datum($datum)]);
    }

    public function getZeitText(): ?string
    {
        if (!$this->zeit_von) {
            return null;
        }
        return $this->zeit_bis
            ? Yii::t('KurseModule.base', '{von}–{bis} Uhr', ['von' => substr($this->zeit_von, 0, 5), 'bis' => substr($this->zeit_bis, 0, 5)])
            : Yii::t('KurseModule.base', '{von} Uhr', ['von' => substr($this->zeit_von, 0, 5)]);
    }

    /** z. B. „Dienstag, 13.01.2027 · 15:00–15:45 Uhr · 10 Einheiten“ (Format der aktuellen Sprache) */
    public function getTermineText(): string
    {
        $teile = [$this->ende
            ? Yii::t('KurseModule.base', '{beginn} bis {ende}', ['beginn' => self::tagUndDatum($this->beginn), 'ende' => self::tagUndDatum($this->ende)])
            : self::tagUndDatum($this->beginn)];
        if ($z = $this->getZeitText()) {
            $teile[] = $z;
        }
        if ($this->einheiten) {
            $teile[] = Yii::t('KurseModule.base', '{n, plural, =1{# Einheit} other{# Einheiten}}', ['n' => (int)$this->einheiten]);
        }
        return implode(' · ', $teile);
    }

    public function getStatusLabel(): array
    {
        if ($this->status === self::STATUS_ENTWURF) {
            return [Yii::t('KurseModule.base', 'Entwurf (nicht sichtbar)'), 'default'];
        }
        if ($this->status === self::STATUS_ABGESAGT) {
            return [Yii::t('KurseModule.base', 'abgesagt'), 'danger'];
        }
        if ($this->istVorbei()) {
            return [Yii::t('KurseModule.base', 'beendet'), 'default'];
        }
        if ($this->freiePlaetze() > 0) {
            return [Yii::t('KurseModule.base', '{frei} von {plaetze} Plätzen frei', ['frei' => $this->freiePlaetze(), 'plaetze' => $this->plaetze]), 'success'];
        }
        return $this->wartelisteFrei() ? [Yii::t('KurseModule.base', 'ausgebucht – Warteliste'), 'warning'] : [Yii::t('KurseModule.base', 'ausgebucht'), 'danger'];
    }

    public function getUrl(bool $scheme = false): string
    {
        $space = Yii::$app->getModule('kurse')->getSpace();
        return $space ? $space->createUrl('/kurse/kurs/view', ['id' => $this->id], $scheme) : Url::home($scheme);
    }

    /** Eigene Kursseite des Vereins (/kurse/<slug>), falls eingerichtet */
    public function getSeite(): ?KursVerein
    {
        $kv = KursVerein::fuer((int)$this->verein_id);
        return $kv && $kv->hatSeite() ? $kv : null;
    }

    /**
     * Adresse für Eltern: auf der eigenen Seite des Vereins, sonst im Space.
     * @param string $was kurs, anmelden, datenschutz, bestaetigen, status
     */
    public function elternUrl(string $was, array $params = [], bool $scheme = false): string
    {
        if ($seite = $this->getSeite()) {
            return $seite->seiteUrl($was, in_array($was, ['kurs', 'anmelden', 'datenschutz'], true) ? ['id' => $this->id] + $params : $params, $scheme);
        }
        $routen = ['kurs' => ['/kurse/kurs/view', 'id'], 'anmelden' => ['/kurse/anmeldung/create', 'kurs'],
            'datenschutz' => ['/kurse/kurs/datenschutz', 'id'], 'bestaetigen' => ['/kurse/anmeldung/bestaetigen', null],
            'status' => ['/kurse/anmeldung/status', null]];
        [$route, $idParam] = $routen[$was];
        return $this->createUrl($route, $idParam ? [$idParam => $this->id] + $params : $params, $scheme);
    }

    public function createUrl(string $route, array $params = [], bool $scheme = false): string
    {
        $space = Yii::$app->getModule('kurse')->getSpace();
        return $space ? $space->createUrl($route, $params, $scheme) : Url::home($scheme);
    }
}
