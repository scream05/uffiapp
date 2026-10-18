<?php

namespace humhub\modules\vereine\models;

use DateTime;
use DateTimeZone;
use humhub\modules\calendar\helpers\CalendarUtils;
use humhub\modules\calendar\models\CalendarEntry;
use humhub\modules\calendar\models\CalendarEntryType;
use humhub\modules\space\models\Space;
use Yii;
use yii\base\Model;

/**
 * Termin eines Vereins: einfaches Formular, gespeichert als normaler Eintrag im gemeinsamen Kalender.
 * Zeiten werden wie im Kalendermodul behandelt (ganztägig: Ende = Folgetag 0 Uhr, sonst in Systemzeitzone umgerechnet).
 */
class TerminForm extends Model
{
    private const TZ = 'Europe/Berlin';

    public string $titel = '';
    public string $start_datum = '';
    public string $start_zeit = '';
    public string $ende_datum = '';
    public string $ende_zeit = '';
    public bool|int $ganztaegig = false;
    public string $ort = '';
    public string $beschreibung = '';
    /** Hinweis auf ähnliche Stadt-Termine gelesen und trotzdem speichern */
    public bool|int $trotzdem = false;

    public function __construct(public CalendarEntry $entry, $config = [])
    {
        parent::__construct($config);
        if (!$entry->isNewRecord) {
            $this->titel = (string)$entry->title;
            $this->ort = (string)$entry->location;
            $this->beschreibung = (string)$entry->description;
            $this->ganztaegig = (bool)$entry->all_day;
            if ($entry->all_day) {
                $start = new DateTime($entry->start_datetime);
                $end = (new DateTime($entry->end_datetime))->modify('-1 second');
            } else {
                $start = new DateTime(CalendarUtils::translateTimezone($entry->start_datetime, CalendarUtils::getSystemTimeZone(), self::TZ));
                $end = new DateTime(CalendarUtils::translateTimezone($entry->end_datetime, CalendarUtils::getSystemTimeZone(), self::TZ));
            }
            [$this->start_datum, $this->start_zeit] = [$start->format('Y-m-d'), $start->format('H:i')];
            [$this->ende_datum, $this->ende_zeit] = [$end->format('Y-m-d'), $end->format('H:i')];
        }
    }

    public function rules()
    {
        return [
            [['titel', 'ort'], 'trim'],
            [['titel', 'start_datum'], 'required'],
            ['titel', 'string', 'min' => 3, 'max' => 200],
            ['ort', 'string', 'max' => 255],
            ['beschreibung', 'string', 'max' => 20000],
            [['start_datum', 'ende_datum'], 'date', 'format' => 'php:Y-m-d'],
            [['start_zeit', 'ende_zeit'], 'match', 'pattern' => '/^\d{1,2}:\d{2}$/', 'message' => Yii::t('VereineModule.base', 'Bitte als hh:mm angeben.')],
            ['start_zeit', 'required', 'when' => fn() => !$this->ganztaegig, 'message' => Yii::t('VereineModule.base', 'Bitte eine Uhrzeit angeben oder „ganztägig“ wählen.')],
            [['ganztaegig', 'trotzdem'], 'boolean'],
            ['ende_datum', 'validateEnd', 'skipOnEmpty' => false],
        ];
    }

    public function attributeLabels()
    {
        return [
            'titel' => Yii::t('VereineModule.base', 'Titel'),
            'start_datum' => Yii::t('VereineModule.base', 'Datum'),
            'start_zeit' => Yii::t('VereineModule.base', 'Beginn'),
            'ende_datum' => Yii::t('VereineModule.base', 'Ende (Datum)'),
            'ende_zeit' => Yii::t('VereineModule.base', 'Ende (Uhrzeit)'),
            'ganztaegig' => Yii::t('VereineModule.base', 'ganztägig'),
            'ort' => Yii::t('VereineModule.base', 'Ort'),
            'beschreibung' => Yii::t('VereineModule.base', 'Beschreibung'),
        ];
    }

    public function validateEnd()
    {
        [$start, $end] = $this->range();
        if ($start && $end && $end <= $start) {
            $this->addError('ende_datum', Yii::t('VereineModule.base', 'Das Ende muss nach dem Beginn liegen.'));
        }
    }

    /** @return DateTime[]|null[] Beginn und Ende in Ortszeit (ganztägig: Ende = Folgetag 0 Uhr) */
    private function range(): array
    {
        $tz = new DateTimeZone(self::TZ);
        $endDate = $this->ende_datum ?: $this->start_datum;
        if ($this->ganztaegig) {
            $start = DateTime::createFromFormat('!Y-m-d', $this->start_datum, $tz);
            $end = DateTime::createFromFormat('!Y-m-d', $endDate, $tz);
            return [$start ?: null, $end ? $end->modify('+1 day') : null];
        }
        $start = DateTime::createFromFormat('Y-m-d H:i', "$this->start_datum $this->start_zeit", $tz) ?: null;
        if ($this->ende_zeit === '') {
            // Ohne Endzeit zwei Stunden (HumHub verlangt ein Ende nach dem Beginn)
            $end = $start ? (clone $start)->modify('+2 hours') : null;
        } else {
            $end = DateTime::createFromFormat('Y-m-d H:i', "$endDate $this->ende_zeit", $tz) ?: null;
        }
        return [$start, $end];
    }

    /** Termine von uffenheim.de am selben Tag, die nach demselben Ereignis aussehen */
    public function similarCityEntries(Space $space): array
    {
        [$start] = $this->range();
        if (!$start) {
            return [];
        }
        $words = fn($s) => array_filter(preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($s)), fn($w) => mb_strlen($w) >= 5);
        $mine = $words($this->titel);
        $day = $start->format('Y-m-d');
        $found = [];
        foreach (CalendarEntry::find()->contentContainer($space)
                     ->andWhere(['like', 'calendar_entry.uid', 'uffenheim-de-%', false])
                     ->andWhere(['<=', 'calendar_entry.start_datetime', "$day 23:59:59"])
                     ->andWhere(['>=', 'calendar_entry.end_datetime', "$day 00:00:00"])->all() as $e) {
            if (array_intersect($mine, $words($e->title)) || similar_text(mb_strtolower($e->title), mb_strtolower($this->titel)) > 0.6 * mb_strlen($this->titel)) {
                $found[] = $e;
            }
        }
        return $found;
    }

    public function save(Verein $verein, ?CalendarEntryType $type): bool
    {
        if (!$this->validate()) {
            return false;
        }
        [$start, $end] = $this->range();
        $entry = $this->entry;
        $isNew = $entry->isNewRecord;
        if ($isNew) {
            $entry->time_zone = self::TZ;
            $entry->content->visibility = $entry->content->container->getDefaultContentVisibility();
        }
        $entry->title = $this->titel;
        $entry->location = $this->ort;
        $entry->description = $this->beschreibung;
        $entry->all_day = $this->ganztaegig ? 1 : 0;
        if ($this->ganztaegig) {
            $entry->start_datetime = $start->format('Y-m-d H:i:s');
            $entry->end_datetime = $end->format('Y-m-d H:i:s');
        } else {
            $entry->start_datetime = CalendarUtils::translateToSystemTimezone($start, self::TZ);
            $entry->end_datetime = CalendarUtils::translateToSystemTimezone($end, self::TZ);
        }
        if ($isNew) {
            $entry->setDefaults();
        }
        if (!$entry->save()) {
            foreach ($entry->getErrors() as $errors) {
                $this->addError('titel', implode(' ', $errors));
            }
            return false;
        }
        if ($isNew) {
            if ($type) {
                $entry->setType($type);
                $entry->updateAttributes(['color' => $type->color]); // Kalender färbt nach Termin-, nicht Kategoriefarbe
            }
            (new VereinTermin(['calendar_entry_id' => $entry->id, 'verein_id' => $verein->id]))->save(false);
        }
        return true;
    }
}
