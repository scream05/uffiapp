<?php

namespace humhub\modules\kiAssistent\components;

use humhub\modules\calendar\models\CalendarEntry;
use humhub\modules\external_calendar\models\ExternalCalendarEntry;
use humhub\modules\post\models\Post;
use humhub\modules\space\models\Space;
use Yii;
use yii\helpers\Url;

/**
 * Wissen für den Assistenten: nur öffentliche Inhalte der Stadt-Spaces, keine Nutzerdaten.
 * Wird 10 Minuten zwischengespeichert, die Quellen ändern sich höchstens stündlich.
 */
class Context
{
    private const DAYS = ['So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa'];

    public static function get(): string
    {
        // Gemeinsamer Teil zwischengespeichert; Mängelmeldungen je Nutzer (nur was er im Mängelmelder lesen darf)
        $base = Yii::$app->cache->getOrSet('ki-assistent-context', fn() => self::build(), 600);
        $maengel = self::maengel();
        // Datum und Uhrzeit außerhalb des Zwischenspeichers (Notdienst wechselt morgens)
        return "Heute ist " . self::DAYS[date('w')] . ', ' . date('d.m.Y') . ', jetzt ist es ' . date('G:i') . " Uhr.\n\n"
            . $base . ($maengel !== null ? "\n\n" . $maengel : '');
    }

    private static function build(): string
    {
        $today = date('Y-m-d');
        $parts = [];

        $space = Space::findOne(['url' => 'abfall-and-termine']);
        if ($space !== null) {
            $lines = [];
            $entries = ExternalCalendarEntry::find()->contentContainer($space)
                ->andWhere(['>=', 'external_calendar_entry.start_datetime', $today])
                ->andWhere(['<', 'external_calendar_entry.start_datetime', date('Y-m-d', strtotime('+60 days'))])
                ->orderBy(['external_calendar_entry.start_datetime' => SORT_ASC])->all();
            foreach ($entries as $e) {
                $lines[] = '- ' . self::date($e->start_datetime, false) . ': ' . $e->title;
            }
            $parts[] = "## Abfuhrtermine (stadtweit, nächste 60 Tage)\n"
                . "Quelle: Landkreis Neustadt a.d.Aisch-Bad Windsheim. Termine für die eigene Straße: "
                . "https://www.kreis-nea.de/lebenslagen/abfallwirtschaft/abfalltermine\n"
                . "Kalender in der Community: " . $space->createUrl('/calendar/view/index', [], true) . "\n"
                . (Yii::$app->hasModule('abfuhr') ? 'Persönliche Erinnerung am Vorabend oder Morgen (Benachrichtigung/Push, Kernstadt oder Ortsteil, Tonnen wählbar): '
                    . Url::to(['/abfuhr/einstellungen'], true) . "\n" : '')
                . ($lines ? implode("\n", $lines) : '(keine Termine)');
        }

        if (Yii::$app->hasModule('notdienst')) {
            $parts[] = \humhub\modules\notdienst\components\Notdienst::forAssistant();
        }

        $space = Space::findOne(['name' => 'Veranstaltungen']);
        if ($space !== null) {
            $lines = [];
            $entries = CalendarEntry::find()->contentContainer($space)
                ->andWhere(['>=', 'calendar_entry.end_datetime', $today])
                ->andWhere(['<', 'calendar_entry.start_datetime', date('Y-m-d', strtotime('+90 days'))])
                ->orderBy(['calendar_entry.start_datetime' => SORT_ASC])->limit(80)->all();
            $vereine = self::vereinByEntry(array_map(fn($e) => $e->id, $entries));
            foreach ($entries as $e) {
                $lines[] = '- ' . self::date($e->start_datetime, !$e->all_day)
                    . (substr($e->end_datetime, 0, 10) > substr($e->start_datetime, 0, 10) ? ' bis ' . self::date($e->end_datetime, false) : '')
                    . ': ' . $e->title . ($e->location ? " (Ort: {$e->location})" : '')
                    . (isset($vereine[$e->id]) ? " (Veranstalter: {$vereine[$e->id]})" : '')
                    . ' – ' . Url::to($e->content->getUrl(), true)
                    . (($d = self::plain($e->description, 300)) !== '' ? "\n  $d" : '');
            }
            $parts[] = "## Veranstaltungen (nächste 90 Tage: Veranstaltungskalender von uffenheim.de und Termine der Vereine)\n"
                . ($lines ? implode("\n", $lines) : '(keine Termine)');
        }

        $space = Space::findOne(['name' => 'Rathaus Uffenheim']);
        if ($space !== null) {
            $lines = [];
            $posts = Post::find()->contentContainer($space)
                ->orderBy(['content.created_at' => SORT_DESC])->limit(15)->all();
            foreach ($posts as $p) {
                $lines[] = '- ' . self::date($p->content->created_at, false) . ' – ' . Url::to($p->content->getUrl(), true)
                    . "\n  " . self::plain($p->message, 700);
            }
            $parts[] = "## Aktuelle Nachrichten der Stadt Uffenheim (neueste zuerst)\n"
                . ($lines ? implode("\n", $lines) : '(keine Nachrichten)');
        }

        if (Yii::$app->hasModule('vereine') && Yii::$app->getModule('vereine') !== null) {
            $lines = [];
            foreach (\humhub\modules\vereine\models\Verein::find()->where(['!=', 'status', 'vorgeschlagen'])
                         ->orderBy(['name' => SORT_ASC])->all() as $v) {
                // Nur die Liste; Beschreibung, Treffpunkt, Ansprechpartner findet die Suche im Stadtwissen (wissen/community/vereine)
                $lines[] = "- {$v->name} ({$v->kategorie}, {$v->ortsteil}) – " . $v->getUrl(true);
            }
            if ($lines) {
                $parts[] = "## Vereinsverzeichnis der Community (Liste; Details zu passenden Vereinen ggf. weiter unten bei den Informationen)\n"
                    . implode("\n", $lines);
            }
        }

        $lines = [];
        foreach (Space::find()->where(['!=', 'visibility', Space::VISIBILITY_NONE])->andWhere(['status' => Space::STATUS_ENABLED])->all() as $s) {
            $lines[] = "- {$s->name}: " . self::plain((string)$s->description, 200) . ' – ' . $s->createUrl('/space/space', [], true);
        }
        $parts[] = "## Bereiche (Spaces) der Community\n" . implode("\n", $lines);

        return implode("\n\n", $parts);
    }

    /** @return array Kalendereintrag-ID => Vereinsname (Termine, die ein Verein über das Vereinsverzeichnis eingetragen hat) */
    /** Mängelmeldungen: offene und in den letzten 30 Tagen abgeschlossene, ohne Namen der Meldenden */
    private static function maengel(): ?string
    {
        if (!Yii::$app->hasModule('maengelmelder')) {
            return null;
        }
        $space = null;
        foreach (Space::find()->where(['!=', 'visibility', Space::VISIBILITY_NONE])->all() as $s) {
            if ($s->moduleManager->isEnabled('maengelmelder')) {
                $space = $s;
            }
        }
        if ($space === null) {
            return null;
        }
        $posts = Post::find()->contentContainer($space)->readable()
            ->andWhere(['like', 'post.message', 'maengelmelder/meldung/karte?lat='])
            ->orderBy(['content.created_at' => SORT_DESC])->limit(150)->all();
        if (!$posts && !$space->isMember()) {
            return "## Mängelmelder\nMeldungen sieht nur, wer dem Bereich „{$space->name}“ beigetreten ist: " . $space->createUrl('/space/space', [], true);
        }
        $ids = array_map(fn($p) => $p->content->id, $posts);
        $statuses = \humhub\modules\maengelmelder\models\MaengelStatus::find()->where(['content_id' => $ids])->indexBy('content_id')->all();
        $auch = class_exists(\humhub\modules\maengelmelder\models\MaengelAuch::class)
            ? \humhub\modules\maengelmelder\models\MaengelAuch::counts($ids) : [];
        $lines = [];
        foreach ($posts as $p) {
            $id = $p->content->id;
            $status = $statuses[$id] ?? new \humhub\modules\maengelmelder\models\MaengelStatus(['status' => 'gemeldet']);
            $closed = in_array($status->status, ['erledigt', 'abgelehnt'], true);
            if ($closed && $status->updated_at < date('Y-m-d', strtotime('-30 days'))) {
                continue;
            }
            preg_match('/^\*\*Mängelmeldung: (.+?)\*\*\s*(.*?)\s*📍 \*\*Ort:\*\* (.*?)(?: – )?\[/su', $p->message, $m);
            $lines[] = '- ' . ($m[1] ?? 'Meldung') . ' – Ort: ' . (trim($m[3] ?? '') ?: 'siehe Karte')
                . ' – gemeldet ' . self::date($p->content->created_at, false) . ' – Status: ' . $status->getLabel()
                . ($closed || !$status->isNewRecord ? ' (seit ' . self::date($status->updated_at, false) . ')' : '')
                . (!empty($auch[$id]) ? " – {$auch[$id]} weitere betroffen" : '')
                . ' – ' . Url::to($p->content->getUrl(), true)
                . (($d = self::plain($m[2] ?? '', 200)) !== '' ? "\n  $d" : '');
        }
        return "## Mängelmelder: aktuelle Meldungen (offen und in den letzten 30 Tagen erledigt)\n"
            . 'Neue Meldung: ' . $space->createUrl('/maengelmelder/meldung/create', [], true) . ' · Karte: '
            . $space->createUrl('/maengelmelder/meldung/karte', [], true) . "\n"
            . ($lines ? implode("\n", $lines) : '(keine offenen Meldungen)');
    }

    private static function vereinByEntry(array $ids): array
    {
        if ($ids === [] || Yii::$app->db->getTableSchema('verein_termin') === null) {
            return [];
        }
        return (new \yii\db\Query())->select(['v.name', 'vt.calendar_entry_id'])->from('verein_termin vt')
            ->innerJoin('verein v', 'v.id = vt.verein_id')->where(['vt.calendar_entry_id' => $ids])
            ->indexBy('calendar_entry_id')->column();
    }

    private static function date(string $datetime, bool $withTime): string
    {
        $t = strtotime($datetime);
        return self::DAYS[date('w', $t)] . ' ' . date('d.m.Y', $t) . ($withTime ? date(', H:i', $t) . ' Uhr' : '');
    }

    /** Markdown grob in Klartext, Bilder weg, auf $max Zeichen gekürzt */
    private static function plain(string $markdown, int $max): string
    {
        $text = preg_replace('/!\[[^\]]*\]\([^)]*\)/', '', $markdown);
        $text = preg_replace('/\[([^\]]*)\]\(([^)]*)\)/', '$1 ($2)', $text);
        $text = trim(preg_replace('/\s+/u', ' ', str_replace(['**', '__', '#'], '', $text)));
        return mb_strlen($text) > $max ? mb_substr($text, 0, $max) . '…' : $text;
    }
}
