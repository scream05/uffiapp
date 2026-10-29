<?php

namespace humhub\modules\whatsapp\models;

use DateTime;
use DateTimeZone;
use humhub\modules\calendar\helpers\CalendarUtils;
use humhub\modules\calendar\models\CalendarEntry;
use humhub\modules\calendar\models\CalendarEntryType;
use humhub\modules\file\models\File;
use humhub\modules\space\models\Space;
use yii\db\ActiveRecord;

/**
 * Von Claude erkannter Termin aus einer WhatsApp-Einreichung; Admins prüfen, korrigieren und übernehmen ihn
 * als normalen Eintrag in den gemeinsamen Kalender (Zeiten wie im Kalendermodul bzw. bei den Vereinsterminen).
 *
 * @property int $id
 * @property string $gruppe
 * @property string|null $wa_id
 * @property string|null $absender_name
 * @property string $titel
 * @property string|null $start_datum
 * @property string|null $start_zeit
 * @property string|null $ende_datum
 * @property string|null $ende_zeit
 * @property int $ganztaegig
 * @property string|null $ort
 * @property string|null $veranstalter
 * @property string|null $beschreibung
 * @property string|null $kategorie
 * @property int|null $bild
 * @property string|null $hinweis
 * @property string $status
 * @property int|null $calendar_entry_id
 * @property string $created_at
 * @property string|null $bearbeitet_at
 * @property int|null $bearbeitet_by
 */
class Entwurf extends ActiveRecord
{
    public const STATUS_OFFEN = 'offen';
    public const STATUS_UEBERNOMMEN = 'uebernommen';
    public const STATUS_ABGELEHNT = 'abgelehnt';

    private const TZ = 'Europe/Berlin';

    /** Hinweis auf ähnliche Termine gelesen und trotzdem übernehmen */
    public bool|int $trotzdem = false;

    public static function tableName()
    {
        return 'wa_entwurf';
    }

    public function rules()
    {
        return [
            [['titel', 'ort', 'veranstalter', 'start_zeit', 'ende_zeit', 'ende_datum'], 'trim'],
            [['titel', 'start_datum'], 'required'],
            ['titel', 'string', 'min' => 3, 'max' => 200],
            [['ort'], 'string', 'max' => 255],
            [['veranstalter'], 'string', 'max' => 200],
            ['beschreibung', 'string', 'max' => 20000],
            ['kategorie', 'string', 'max' => 100],
            [['start_datum', 'ende_datum'], 'date', 'format' => 'php:Y-m-d'],
            [['start_zeit', 'ende_zeit'], 'match', 'pattern' => '/^\d{1,2}:\d{2}$/', 'message' => 'Bitte als hh:mm angeben.'],
            ['start_zeit', 'required', 'when' => fn() => !$this->ganztaegig, 'message' => 'Bitte eine Uhrzeit angeben oder „ganztägig“ wählen.'],
            [['ganztaegig', 'trotzdem'], 'boolean'],
            [['ende_datum', 'ende_zeit', 'ort', 'veranstalter', 'beschreibung', 'kategorie', 'start_zeit'], 'default', 'value' => null],
            ['ende_datum', 'validateEnd', 'skipOnEmpty' => false],
        ];
    }

    public function attributeLabels()
    {
        return [
            'titel' => 'Titel',
            'start_datum' => 'Datum',
            'start_zeit' => 'Beginn',
            'ende_datum' => 'Ende (Datum)',
            'ende_zeit' => 'Ende (Uhrzeit)',
            'ganztaegig' => 'ganztägig',
            'ort' => 'Ort',
            'veranstalter' => 'Veranstalter',
            'beschreibung' => 'Beschreibung',
            'kategorie' => 'Kategorie',
        ];
    }

    public function validateEnd()
    {
        [$start, $end] = $this->range();
        if ($start === null) {
            return;
        }
        if ($end === null) {
            $this->addError('ende_datum', 'Ende ungültig.');
        } elseif ($end <= $start) {
            $this->addError('ende_datum', 'Das Ende muss nach dem Beginn liegen.');
        }
    }

    /** @return DateTime[]|null[] Beginn und Ende in Ortszeit (ganztägig: Ende = Folgetag 0 Uhr) */
    public function range(): array
    {
        $tz = new DateTimeZone(self::TZ);
        $endDate = $this->ende_datum ?: $this->start_datum;
        if ($this->ganztaegig) {
            $start = DateTime::createFromFormat('!Y-m-d', (string)$this->start_datum, $tz);
            $end = DateTime::createFromFormat('!Y-m-d', (string)$endDate, $tz);
            return [$start ?: null, $end ? $end->modify('+1 day') : null];
        }
        $start = DateTime::createFromFormat('Y-m-d H:i', "$this->start_datum $this->start_zeit", $tz) ?: null;
        if (!$this->ende_zeit && $this->ende_datum && $this->ende_datum > $this->start_datum) {
            // Mehrtägig ohne Endzeit: bis zum Ende des letzten Tags
            $end = DateTime::createFromFormat('Y-m-d H:i', "$this->ende_datum 23:59", $tz) ?: null;
        } elseif (!$this->ende_zeit) {
            // Ohne Endzeit zwei Stunden (HumHub verlangt ein Ende nach dem Beginn)
            $end = $start ? (clone $start)->modify('+2 hours') : null;
        } else {
            $end = DateTime::createFromFormat('Y-m-d H:i', "$endDate $this->ende_zeit", $tz) ?: null;
        }
        return [$start, $end];
    }

    /** Termine im Kalender am selben Tag, die nach demselben Ereignis aussehen (Stadt-Import, Vereine, frühere Einreichungen) */
    public function aehnliche(Space $space): array
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
                     ->andWhere(['<=', 'calendar_entry.start_datetime', "$day 23:59:59"])
                     ->andWhere(['>=', 'calendar_entry.end_datetime', "$day 00:00:00"])->all() as $e) {
            if (array_intersect($mine, $words($e->title))
                || similar_text(mb_strtolower($e->title), mb_strtolower($this->titel)) > 0.6 * mb_strlen($this->titel)) {
                $found[] = $e;
            }
        }
        return $found;
    }

    /** Nachrichten (Bilder/PDFs) der Einreichung, in Eingangsreihenfolge */
    public function getMedien(): array
    {
        return Nachricht::find()->where(['gruppe' => $this->gruppe])->andWhere(['not', ['datei' => null]])
            ->orderBy(['empfangen_at' => SORT_ASC, 'id' => SORT_ASC])->all();
    }

    /** Text der Einreichung (Bildunterschriften und Textnachrichten) */
    public function getOriginaltext(): string
    {
        $texts = Nachricht::find()->select('text')->where(['gruppe' => $this->gruppe])->andWhere(['not', ['text' => null]])
            ->orderBy(['empfangen_at' => SORT_ASC, 'id' => SORT_ASC])->column();
        return trim(implode("\n\n", $texts));
    }

    /** Als Kalendereintrag anlegen; Ersteller ist der freigebende Admin */
    public function uebernehmen(Space $space): ?CalendarEntry
    {
        if (!$this->validate()) {
            return null;
        }
        [$start, $end] = $this->range();
        $entry = new CalendarEntry($space);
        $entry->time_zone = self::TZ;
        $entry->content->visibility = $space->getDefaultContentVisibility();
        $entry->title = $this->titel;
        $entry->location = (string)$this->ort;
        $beschreibung = trim((string)$this->beschreibung);
        if ($this->veranstalter) {
            $beschreibung = "**Veranstalter:** $this->veranstalter\n\n" . $beschreibung;
        }
        $entry->description = trim($beschreibung . "\n\n*Eingereicht über die WhatsApp-Community.*");
        $entry->all_day = $this->ganztaegig ? 1 : 0;
        if ($this->ganztaegig) {
            $entry->start_datetime = $start->format('Y-m-d H:i:s');
            $entry->end_datetime = $end->format('Y-m-d H:i:s');
        } else {
            $entry->start_datetime = CalendarUtils::translateToSystemTimezone($start, self::TZ);
            $entry->end_datetime = CalendarUtils::translateToSystemTimezone($end, self::TZ);
        }
        $entry->setDefaults();
        if (!$entry->save()) {
            foreach ($entry->getErrors() as $errors) {
                $this->addError('titel', implode(' ', $errors));
            }
            return null;
        }
        if ($this->kategorie && ($type = self::typ($space, $this->kategorie))) {
            $entry->setType($type);
            $entry->updateAttributes(['color' => $type->color]); // Kalender färbt nach Termin-, nicht Kategoriefarbe
        }
        // Das zugeordnete Bild (Nummer wie bei der Auswertung: alle Dateien der Einreichung), sonst alle Bilder
        $alle = $this->getMedien();
        $medien = $this->bild !== null && isset($alle[$this->bild]) && $alle[$this->bild]->isBild()
            ? [$alle[$this->bild]] : array_filter($alle, fn(Nachricht $n) => $n->isBild());
        foreach ($medien as $n) {
            self::anhaengen($entry, $n);
        }
        $this->status = self::STATUS_UEBERNOMMEN;
        $this->calendar_entry_id = $entry->id;
        $this->bearbeitet_at = date('Y-m-d H:i:s');
        $this->bearbeitet_by = \Yii::$app->user->id;
        $this->save(false);
        return $entry;
    }

    public function ablehnen(): void
    {
        $this->updateAttributes(['status' => self::STATUS_ABGELEHNT, 'bearbeitet_at' => date('Y-m-d H:i:s'), 'bearbeitet_by' => \Yii::$app->user->id]);
    }

    public static function typen(Space $space): array
    {
        return CalendarEntryType::find()->where(['content_tag.module_id' => 'calendar',
            'content_tag.contentcontainer_id' => $space->contentcontainer_id])->orderBy('content_tag.name')->all();
    }

    private static function typ(Space $space, string $name): ?CalendarEntryType
    {
        foreach (self::typen($space) as $type) {
            if ($type->name === $name) {
                return $type;
            }
        }
        return null;
    }

    private static function anhaengen(CalendarEntry $entry, Nachricht $n): void
    {
        $data = @file_get_contents($n->getPfad());
        if ($data === false) {
            return;
        }
        $file = new File([
            'file_name' => 'plakat-' . $n->id . '.' . (explode('/', (string)$n->mime)[1] ?? 'jpg'),
            'mime_type' => $n->mime,
            'size' => strlen($data),
            'title' => mb_substr('Plakat: ' . $entry->title, 0, 250), // Bildbeschreibung (Alt-Text), siehe designwahl Bildtext
        ]);
        $file->save();
        $file->setStoredFileContent($data);
        $entry->fileManager->attach($file);
    }
}
