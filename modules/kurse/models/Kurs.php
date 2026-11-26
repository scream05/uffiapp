<?php

namespace humhub\modules\kurse\models;

use humhub\components\ActiveRecord;
use humhub\modules\vereine\models\Verein;
use humhub\modules\kurse\components\Inhalt;
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
 * @property string $beginn bei beginn_offen nur ungefähr (Sortierung/Jahr), wird nicht angezeigt
 * @property bool $beginn_offen
 * @property string|null $beginn_text z. B. „voraussichtlich im Januar 2027“
 * @property string|null $ende
 * @property string|null $wochentage ISO-Wochentage kommagetrennt, z. B. „1,6“
 * @property string|null $tageszeiten JSON: abweichende Uhrzeit je Kurstag, z. B. {"6":{"von":"11:15","bis":"12:00"}}
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
            [['beginn_text'], 'string', 'max' => 100],
            [['beginn_offen'], 'boolean'],
            ['beginn_text', 'required', 'when' => fn($m) => (bool)$m->beginn_offen, 'whenClient' => "function(){return \$('#kurs-beginn_offen').is(':checked');}",
                'message' => Yii::t('KurseModule.base', 'Bitte kurz beschreiben, wann der Kurs voraussichtlich beginnt.')],
            [['niveau', 'kontoinhaber'], 'string', 'max' => 100],
            [['ort', 'termine_hinweis'], 'string', 'max' => 255],
            [['beschreibung', 'bedingungen'], 'string', 'max' => 10000],
            [['titel', 'beginn_text', 'niveau', 'ort', 'termine_hinweis', 'kontoinhaber', 'iban', 'kontakt_email', 'beschreibung', 'bedingungen'], 'trim'],
            [['beginn', 'ende'], 'date', 'format' => 'php:Y-m-d'],
            ['wochentageListe', 'each', 'rule' => ['in', 'range' => [1, 2, 3, 4, 5, 6, 7]]],
            ['wochentageListe', 'validateWochentage'],
            ['tageszeitenListe', 'validateTageszeiten'],
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
                'preis_nichtmitglied', 'zahlungsfrist_tage', 'erinnerung_tage', 'niveau', 'beginn_text', 'termine_hinweis', 'kontoinhaber',
                'beschreibung', 'bedingungen'], 'default', 'value' => null],
        ];
    }

    /** Erster und letzter Kurstag müssen auf einen angekreuzten Kurstag fallen */
    public function validateWochentage()
    {
        $tage = $this->getWochentageListe();
        if (!$tage) {
            return;
        }
        foreach ($this->beginn_offen ? ['ende'] : ['beginn', 'ende'] as $feld) {
            if ($this->$feld && !$this->hasErrors($feld) && !in_array((int)date('N', strtotime($this->$feld)), $tage, true)) {
                $this->addError('wochentageListe', Yii::t('KurseModule.base', '{feld} ist ein {wochentag}. Bitte diesen Tag mit ankreuzen oder das Datum anpassen.', [
                    'feld' => $this->getAttributeLabel($feld), 'wochentag' => Yii::$app->formatter->asDate($this->$feld, 'EEEE')]));
            }
        }
    }

    /** Uhrzeiten je Kurstag prüfen: Format hh:mm, Ende nach Beginn, „bis“ nur mit „von“ */
    public function validateTageszeiten()
    {
        $namen = self::wochentagNamen();
        foreach ($this->getTageszeitenListe() as $tag => $z) {
            foreach (['von', 'bis'] as $k) {
                if ($z[$k] !== '' && !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $z[$k])) {
                    $this->addError('tageszeitenListe', Yii::t('KurseModule.base', '{tag}: Bitte die Uhrzeit als hh:mm angeben.', ['tag' => $namen[$tag]]));
                    continue 2;
                }
            }
            if ($z['von'] === '' && $z['bis'] !== '') {
                $this->addError('tageszeitenListe', Yii::t('KurseModule.base', '{tag}: Bitte auch die Anfangszeit angeben.', ['tag' => $namen[$tag]]));
            } elseif ($z['bis'] !== '' && $z['bis'] <= $z['von']) {
                $this->addError('tageszeitenListe', Yii::t('KurseModule.base', '{tag}: Das Ende muss nach dem Anfang liegen.', ['tag' => $namen[$tag]]));
            }
        }
    }

    /**
     * Abweichende Uhrzeiten je Kurstag: [6 => ['von' => '11:15', 'bis' => '12:00'], …].
     * Nur angekreuzte Kurstage, nur Tage mit wenigstens einer Angabe.
     */
    public function getTageszeitenListe(): array
    {
        $daten = json_decode((string)$this->tageszeiten, true);
        if (!is_array($daten)) {
            return [];
        }
        $tage = $this->getWochentageListe();
        $liste = [];
        foreach ($daten as $tag => $z) {
            $tag = (int)$tag;
            if (!in_array($tag, $tage, true) || !is_array($z)) {
                continue;
            }
            $von = substr(trim((string)($z['von'] ?? '')), 0, 5);
            $bis = substr(trim((string)($z['bis'] ?? '')), 0, 5);
            if ($von !== '' || $bis !== '') {
                $liste[$tag] = ['von' => $von, 'bis' => $bis];
            }
        }
        ksort($liste);
        return $liste;
    }

    /** Aus dem Formular: Kurs[tageszeitenListe][6][von] … (Tage ohne Haken werden beim Lesen ignoriert) */
    public function setTageszeitenListe($werte): void
    {
        $daten = [];
        foreach (is_array($werte) ? $werte : [] as $tag => $z) {
            $tag = (int)$tag;
            if ($tag < 1 || $tag > 7 || !is_array($z)) {
                continue;
            }
            $von = trim((string)($z['von'] ?? ''));
            $bis = trim((string)($z['bis'] ?? ''));
            if ($von !== '' || $bis !== '') {
                $daten[$tag] = ['von' => $von, 'bis' => $bis];
            }
        }
        ksort($daten);
        $this->tageszeiten = $daten ? json_encode($daten) : null;
    }

    /** Kurstage als Zahlen 1 (Mo) … 7 (So), sortiert */
    public function getWochentageListe(): array
    {
        $tage = array_map('intval', array_filter(explode(',', (string)$this->wochentage), 'strlen'));
        $tage = array_values(array_unique(array_filter($tage, fn($t) => $t >= 1 && $t <= 7)));
        sort($tage);
        return $tage;
    }

    /** Aus dem Formular (checkboxList, leer = '') */
    public function setWochentageListe($werte): void
    {
        $tage = array_map('intval', is_array($werte) ? $werte : []);
        $tage = array_values(array_unique(array_filter($tage, fn($t) => $t >= 1 && $t <= 7)));
        sort($tage);
        $this->wochentage = $tage ? implode(',', $tage) : null;
    }

    /** Auswahl für das Formular: 1 => „Montag“ … 7 => „Sonntag“ in der Sprache der Oberfläche */
    public static function wochentagNamen(string $format = 'EEEE'): array
    {
        $namen = [];
        for ($t = 1; $t <= 7; $t++) {
            // 2024-01-01 war ein Montag
            $namen[$t] = Yii::$app->formatter->asDate('2024-01-0' . $t, $format);
        }
        return $namen;
    }

    /** z. B. „Mo. + Sa.“, leer wenn keine Kurstage angegeben */
    public function getWochentageText(): ?string
    {
        $tage = $this->getWochentageListe();
        if (!$tage) {
            return null;
        }
        $namen = self::wochentagNamen('EEE');
        $zeiten = $this->getTageszeitenListe();
        if (!$zeiten) {
            return implode(' + ', array_map(fn($t) => $namen[$t], $tage));
        }
        // Abweichende Uhrzeiten: jeder Tag mit seiner Zeit (sonst die allgemeine Uhrzeit des Kurses)
        return implode(' + ', array_map(function ($t) use ($namen, $zeiten) {
            $z = isset($zeiten[$t]) && $zeiten[$t]['von'] !== ''
                ? self::zeitText($zeiten[$t]['von'], $zeiten[$t]['bis'])
                : $this->getZeitText();
            return $z ? $namen[$t] . ' ' . $z : $namen[$t];
        }, $tage));
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
            'beginn_offen' => Yii::t('KurseModule.base', 'Kursbeginn steht noch nicht fest'),
            'beginn_text' => Yii::t('KurseModule.base', 'Voraussichtlicher Beginn'),
            'wochentageListe' => Yii::t('KurseModule.base', 'Kurstage'),
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

    /** Seepferdchen (eigene Zeichnung, es gibt kein Seepferdchen-Emoji), Höhe wie ein Emoji */
    public const SEEPFERDCHEN_SVG = '<svg viewBox="8 0 48 64" style="height:1.2em;width:auto;vertical-align:-0.25em" focusable="false">'
        . '<path d="M26 50 C22 56 24 62 31 62 C37 62 38 55 33 54" fill="none" stroke="#e8890c" stroke-width="5" stroke-linecap="round"/>'
        . '<path d="M23 30 C16 28 12 35 17 40 L24 39 Z" fill="#ffd36b" stroke="#c96f0a" stroke-width="1.8" stroke-linejoin="round"/>'
        . '<path d="M26 4 C31 3 35 6 35 10 L47 13 C51 14 51 20 47 20 L37 21 C36 23 37 25 40 28 C46 34 46 44 39 49 C35 52 30 52 26 53 C23 48 23 45 25 41 C27 37 25 33 22 30 C17 26 16 18 19 12 C21 8 22 5 26 4 Z" fill="#f7a823" stroke="#c96f0a" stroke-width="2.2" stroke-linejoin="round"/>'
        . '<path d="M22 7 L19 2 L25 4.5 M29 3.5 L31 0.5 L33 6" fill="#f7a823" stroke="#c96f0a" stroke-width="1.6" stroke-linejoin="round" stroke-linecap="round"/>'
        . '<path d="M37 31 L42 30 M39 36 L44 36 M38 42 L43 43 M34 47 L37 50" fill="none" stroke="#c96f0a" stroke-width="1.6" stroke-linecap="round"/>'
        . '<circle cx="30" cy="13" r="3.4" fill="#fff" stroke="#c96f0a" stroke-width="1.2"/>'
        . '<circle cx="31" cy="13" r="1.7" fill="#2a2a2a"/></svg>';

    /** Kleines Symbol vor dem Kurstitel zur Unterscheidung (Schlüsselwort im deutschen Titel), sonst leer */
    public function getSymbol(): string
    {
        $t = mb_strtolower((string)$this->titel);
        if (str_contains($t, 'seepferd')) {
            return 'seepferdchen'; // kein Emoji vorhanden → eigenes SVG, siehe getSymbolHtml()
        }
        if (str_contains($t, 'seeräuber')) {
            return '🦜';
        }
        if (str_contains($t, 'pirat')) {
            return '🏴‍☠️';
        }
        if (str_contains($t, 'anfänger')) {
            return '🐠';
        }
        return '';
    }

    /** Symbol als HTML vor dem Titel (für Screenreader ausgeblendet) */
    public function getSymbolHtml(): string
    {
        $s = $this->getSymbol();
        if ($s === 'seepferdchen') {
            $s = self::SEEPFERDCHEN_SVG;
        }
        return $s === '' ? '' : '<span class="kurs-symbol" aria-hidden="true">' . $s . '</span> ';
    }

    /** Letzter Kurstag (angegeben oder aus Beginn + Einheiten geschätzt: an den Kurstagen, sonst wöchentlich) */
    public function getEndeDatum(): string
    {
        if ($this->ende) {
            return $this->ende;
        }
        $tage = $this->getWochentageListe();
        if ($tage && $this->einheiten > 1) {
            $tag = strtotime($this->beginn);
            $rest = (int)$this->einheiten - (in_array((int)date('N', $tag), $tage, true) ? 1 : 0);
            for ($i = 0; $rest > 0 && $i < 3000; $i++) {
                $tag = strtotime('+1 day', $tag);
                if (in_array((int)date('N', $tag), $tage, true)) {
                    $rest--;
                }
            }
            return date('Y-m-d', $tag);
        }
        $wochen = max(0, (int)$this->einheiten - 1);
        return date('Y-m-d', strtotime($this->beginn . " +$wochen weeks"));
    }

    public function istVorbei(): bool
    {
        if ($this->beginn_offen) {
            return false;
        }
        return $this->getEndeDatum() < date('Y-m-d');
    }

    public function hatBegonnen(): bool
    {
        if ($this->beginn_offen) {
            return false;
        }
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
        return $this->zeit_von ? self::zeitText($this->zeit_von, $this->zeit_bis) : null;
    }

    /** „16:00–16:45 Uhr“ bzw. „16:00 Uhr“ */
    public static function zeitText(string $von, ?string $bis): string
    {
        return $bis
            ? Yii::t('KurseModule.base', '{von}–{bis} Uhr', ['von' => substr($von, 0, 5), 'bis' => substr($bis, 0, 5)])
            : Yii::t('KurseModule.base', '{von} Uhr', ['von' => substr($von, 0, 5)]);
    }

    /** z. B. „Dienstag, 13.01.2027 · 15:00–15:45 Uhr · 10 Einheiten“ (Format der aktuellen Sprache) */
    public function getTermineText(): string
    {
        $teile = [$this->beginn_offen
            ? Yii::t('KurseModule.base', 'Beginn: {text}', ['text' => trim(Inhalt::t($this->beginn_text)) ?: Yii::t('KurseModule.base', 'steht noch nicht fest')])
            : ($this->ende
            ? Yii::t('KurseModule.base', '{beginn} bis {ende}', ['beginn' => self::tagUndDatum($this->beginn), 'ende' => self::tagUndDatum($this->ende)])
            : self::tagUndDatum($this->beginn))];
        if ($w = $this->getWochentageText()) {
            $teile[] = Yii::t('KurseModule.base', 'Kurstage: {tage}', ['tage' => $w]);
        }
        // Bei abweichenden Uhrzeiten je Tag steht die Zeit schon bei den Kurstagen
        if (!$this->getTageszeitenListe() && ($z = $this->getZeitText())) {
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
