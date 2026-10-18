<?php

namespace humhub\modules\abfuhr\components;

use humhub\modules\abfuhr\Module;
use humhub\modules\abfuhr\notifications\AbfuhrMorgen;
use humhub\modules\external_calendar\models\ExternalCalendarEntry;
use humhub\modules\space\models\Space;
use humhub\modules\user\models\User;
use Yii;

/**
 * Einstellungen je Nutzer (Modul-Einstellungen des Kontos, werden mit dem Konto gelöscht) und Versand.
 * Gespeichert: aktiv (0/1), gebiet (kernstadt|ortsteil), tonnen (z. B. „rest,bio,papier,gelb“), zeit (18|20|6),
 * gesendet (Abfuhrdatum der letzten Erinnerung, gegen doppelten Versand).
 */
class Erinnerung
{
    public static function module(): Module
    {
        return Yii::$app->getModule('abfuhr');
    }

    /** @return array [aktiv, gebiet, tonnen[], zeit] */
    public static function settings(User $user): array
    {
        $s = self::module()->settings->user($user);
        $tonnen = array_values(array_intersect(explode(',', (string)$s->get('tonnen', 'rest,bio,papier,gelb')), array_keys(self::module()->tonnen)));
        $zeit = (string)$s->get('zeit', '18');
        return [
            (bool)$s->get('aktiv', false),
            $s->get('gebiet') === 'ortsteil' ? 'ortsteil' : 'kernstadt',
            $tonnen,
            isset(self::module()->zeiten[$zeit]) ? $zeit : '18',
        ];
    }

    public static function save(User $user, bool $aktiv, string $gebiet, array $tonnen, string $zeit): void
    {
        $s = self::module()->settings->user($user);
        $s->set('aktiv', $aktiv ? 1 : 0);
        $s->set('gebiet', $gebiet === 'ortsteil' ? 'ortsteil' : 'kernstadt');
        $s->set('tonnen', implode(',', array_intersect($tonnen, array_keys(self::module()->tonnen))));
        $s->set('zeit', isset(self::module()->zeiten[$zeit]) ? $zeit : '18');
    }

    public static function space(): ?Space
    {
        return Space::findOne(['url' => self::module()->spaceUrl]);
    }

    /**
     * Abfuhren ab $from für ein Gebiet: [Y-m-d => [Tonnen-Schlüssel, …]]
     * Gelbe Tonne: Kalendertitel „Gelbe Tonne - Kernstadt“ bzw. „Gelbe Tonne - Ortsteile“.
     */
    public static function termine(string $from, string $to, string $gebiet): array
    {
        $space = self::space();
        if ($space === null) {
            return [];
        }
        $out = [];
        $entries = ExternalCalendarEntry::find()->contentContainer($space)
            ->andWhere(['>=', 'external_calendar_entry.start_datetime', $from])
            ->andWhere(['<', 'external_calendar_entry.start_datetime', date('Y-m-d', strtotime($to . ' +1 day'))])
            ->orderBy(['external_calendar_entry.start_datetime' => SORT_ASC])->all();
        foreach ($entries as $e) {
            $title = trim($e->title);
            foreach (self::module()->tonnen as $key => $label) {
                if (!str_starts_with($title, $label)) {
                    continue;
                }
                if ($key === 'gelb' && stripos($title, $gebiet === 'ortsteil' ? 'Ortsteil' : 'Kernstadt') === false) {
                    continue;
                }
                $out[substr($e->start_datetime, 0, 10)][] = $key;
            }
        }
        foreach ($out as &$keys) {
            $keys = array_values(array_unique($keys));
        }
        return $out;
    }

    /** Text wie „Biotonne und Restmüll“ (deutsch; mit $uebersetzt in der Sprache des Nutzers) */
    public static function liste(array $keys, bool $uebersetzt = false): string
    {
        if ($uebersetzt) {
            $labels = array_map(fn($k) => self::module()->tonneLabel($k), $keys);
            return count($labels) > 1 ? Yii::t('AbfuhrModule.base', '{liste} und {letzte}', [
                'liste' => implode(', ', array_slice($labels, 0, -1)), 'letzte' => end($labels)]) : (string)reset($labels);
        }
        $labels = array_map(fn($k) => self::module()->tonnen[$k] ?? $k, $keys);
        return count($labels) > 1 ? implode(', ', array_slice($labels, 0, -1)) . ' und ' . end($labels) : (string)reset($labels);
    }

    /** Abfuhrtag kurz, z. B. „Mo, 20.10.“ – deutsch fest, andere Sprachen über den Formatter */
    public static function tag(string $ymd, bool $uebersetzt = false): string
    {
        if ($uebersetzt && !str_starts_with(Yii::$app->language, 'de')) {
            return Yii::$app->formatter->asDate($ymd, 'EEE, dd.MM.');
        }
        return ['So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa'][(int)date('w', strtotime($ymd))] . ', ' . date('d.m.', strtotime($ymd));
    }

    /**
     * Fällige Erinnerungen versenden (alle 15 Minuten per Crontab).
     * Fällig, wenn die gewählte Stunde erreicht ist (bis 3 Stunden danach, falls ein Lauf ausfällt) und für diesen
     * Abfuhrtag noch nichts verschickt wurde.
     * @return int Anzahl verschickter Erinnerungen
     */
    public static function run(?int $now = null, bool $dryRun = false): int
    {
        $now ??= time();
        $hour = (int)date('G', $now);
        $sent = 0;
        $userIds = (new \yii\db\Query())->select('c.pk')->from('contentcontainer_setting s')
            ->innerJoin('contentcontainer c', 'c.id = s.contentcontainer_id AND c.class = :cls', [':cls' => User::class])
            ->where(['s.module_id' => 'abfuhr', 's.name' => 'aktiv', 's.value' => '1'])->column();
        foreach (User::find()->where(['id' => $userIds, 'status' => User::STATUS_ENABLED])->all() as $user) {
            [$aktiv, $gebiet, $tonnen, $zeit] = self::settings($user);
            [, $stunde, $offset] = self::module()->zeiten[$zeit];
            if (!$aktiv || !$tonnen || $hour < $stunde || $hour >= $stunde + 3) {
                continue;
            }
            $tag = date('Y-m-d', strtotime("+$offset day", $now));
            $keys = array_values(array_intersect(self::termine($tag, $tag, $gebiet)[$tag] ?? [], $tonnen));
            $s = self::module()->settings->user($user);
            if (!$keys || $s->get('gesendet') === $tag) {
                continue;
            }
            if (!$dryRun) {
                AbfuhrMorgen::instance()->payload(['tag' => $tag, 'tonnen' => $keys, 'heute' => $offset === 0])->send($user);
                $s->set('gesendet', $tag);
            }
            Yii::info("Abfuhr-Erinnerung an Nutzer {$user->id}: $tag " . implode(',', $keys), 'abfuhr');
            $sent++;
        }
        return $sent;
    }
}
