<?php

namespace humhub\modules\wochenblick\components;

use humhub\modules\calendar\models\CalendarEntry;
use humhub\modules\content\models\Content;
use humhub\modules\space\models\Space;
use humhub\modules\user\models\User;
use humhub\modules\wochenblick\Module;
use humhub\modules\wochenblick\notifications\Wochenueberblick;
use Yii;
use yii\helpers\Html;
use yii\helpers\Url;

/**
 * Wochenüberblick: Daten zusammenstellen, Einstellungen je Konto und Versand.
 * Zeitraum = Samstag bis Freitag nach dem Versandtag (am Freitag also Wochenende + kommende Woche).
 * Einstellungen je Konto (Modul-Einstellungen, werden mit dem Konto gelöscht):
 * aktiv (0/1), mail (0/1, Standard 1), gesendet (Datum des letzten Versands, gegen doppelten Versand).
 */
class Ueberblick
{
    public static function module(): Module
    {
        return Yii::$app->getModule('wochenblick');
    }

    /** @return array [aktiv, mail] */
    public static function settings(User $user): array
    {
        $s = self::module()->settings->user($user);
        return [(bool)$s->get('aktiv', false), (bool)$s->get('mail', true)];
    }

    public static function save(User $user, bool $aktiv, bool $mail): void
    {
        $s = self::module()->settings->user($user);
        $s->set('aktiv', $aktiv ? 1 : 0);
        $s->set('mail', $mail ? 1 : 0);
    }

    /** Erster Tag des Zeitraums: der nächste Samstag (an einem Samstag dieser selbst) */
    public static function start(?int $jetzt = null): string
    {
        $jetzt ??= time();
        $tag = (int)date('w', $jetzt);
        return date('Y-m-d', strtotime('+' . ((6 - $tag + 7) % 7) . ' days', $jetzt));
    }

    /**
     * Alle Teile des Überblicks für ein Konto; leere Teile fehlen.
     * @return array{von:string, bis:string, abfuhr:array, gebiet:?string, termine:array, mehrTermine:int, spiele:array, wetter:array, mb:?array, gutscheine:array}
     */
    public static function daten(User $user, ?int $jetzt = null): array
    {
        $jetzt ??= time();
        $von = self::start($jetzt);
        $bis = date('Y-m-d', strtotime($von . ' +6 days')); // Freitag, einschließlich
        [$abfuhr, $gebiet] = self::abfuhr($user, $von, $bis);
        [$termine, $mehr] = self::termine($von, $bis);
        return [
            'von' => $von,
            'bis' => $bis,
            'abfuhr' => $abfuhr,
            'gebiet' => $gebiet,
            'termine' => $termine,
            'mehrTermine' => $mehr,
            'spiele' => self::spiele($von),
            'wetter' => self::wetter($von),
            'mb' => self::mitteilungsblatt($jetzt),
            'gutscheine' => self::gutscheine($jetzt),
        ];
    }

    /** Abfuhren im Zeitraum fürs Gebiet aus der Abfuhr-Erinnerung (sonst Kernstadt): [[tag, text]], gebiet */
    private static function abfuhr(User $user, string $von, string $bis): array
    {
        if (!Yii::$app->hasModule('abfuhr') || !class_exists(\humhub\modules\abfuhr\components\Erinnerung::class)) {
            return [[], null];
        }
        $e = \humhub\modules\abfuhr\components\Erinnerung::class;
        [, $gebiet] = $e::settings($user);
        $out = [];
        foreach ($e::termine($von, $bis, $gebiet) as $tag => $keys) {
            $out[] = [$e::tag($tag, true), $e::liste($keys, true)];
        }
        return [$out, $gebiet];
    }

    /**
     * Öffentliche Termine im Space „Veranstaltungen“, die im Zeitraum beginnen (mehrtägige auch, wenn sie schon laufen)
     * @return array [[[datum, uhrzeit, titel, ort, url, wochenende], …], weitere]
     */
    private static function termine(string $von, string $bis): array
    {
        $space = Space::findOne(['url' => 'veranstaltungen']);
        if ($space === null || !Yii::$app->hasModule('calendar')) {
            return [[], 0];
        }
        $query = CalendarEntry::find()->contentContainer($space)
            ->andWhere(['content.state' => Content::STATE_PUBLISHED, 'content.visibility' => Content::VISIBILITY_PUBLIC, 'content.hidden' => 0])
            ->andWhere(['or', ['content.archived' => 0], ['content.archived' => null]])
            ->andWhere(['>=', 'calendar_entry.end_datetime', $von])
            ->andWhere(['<', 'calendar_entry.start_datetime', date('Y-m-d', strtotime($bis . ' +1 day'))])
            ->orderBy(['calendar_entry.start_datetime' => SORT_ASC]);
        $alle = $query->all();
        $max = self::module()->maxTermine;
        $out = [];
        foreach (array_slice($alle, 0, $max) as $e) {
            $start = substr($e->start_datetime, 0, 10);
            $ende = substr($e->end_datetime, 0, 10);
            $mehrtaegig = $ende > $start && !($e->all_day && $ende === date('Y-m-d', strtotime($start . ' +1 day')));
            $datum = self::tag(max($start, $von));
            if ($mehrtaegig) {
                $datum .= ' – ' . self::tag($e->all_day ? date('Y-m-d', strtotime($ende . ' -1 day')) : $ende);
            }
            $out[] = [
                $datum,
                $e->all_day || $mehrtaegig ? '' : Yii::$app->formatter->asTime($e->start_datetime, 'php:H:i'),
                $e->title,
                (string)$e->location,
                Url::to($e->content->getUrl(), true),
                in_array((int)date('w', strtotime(max($start, $von))), [0, 6], true),
            ];
        }
        return [$out, max(0, count($alle) - $max)];
    }

    /** Spiele aller Vereine mit Spielquelle am Wochenende: [[tag, uhrzeit, text, ort]] */
    private static function spiele(string $samstag): array
    {
        if (!Yii::$app->hasModule('vereine') || Yii::$app->db->getTableSchema('verein_spiel') === null) {
            return [];
        }
        $vereine = \humhub\modules\vereine\models\Verein::find()->where(['not', ['bfv_id' => null]])
            ->orderBy(\humhub\modules\vereine\components\Spielquellen::reihenfolge())->all();
        $label = [];
        foreach ($vereine as $v) {
            foreach ($v->mannschaften as $m) {
                $label[$m->id] = (count($vereine) > 1 ? ($v->bfv_name ?: $v->name) . ' ' : '') . $m->getLabel();
            }
        }
        if (!$label) {
            return [];
        }
        $sonntag = date('Y-m-d', strtotime($samstag . ' +1 day'));
        $out = [];
        foreach (\humhub\modules\vereine\models\VereinSpiel::find()->where(['mannschaft_id' => array_keys($label), 'datum' => [$samstag, $sonntag]])
                     ->orderBy(['datum' => SORT_ASC, 'uhrzeit' => SORT_ASC])->all() as $s) {
            $gegner = $s->heim_eigen ? $s->gast : $s->heim;
            $out[] = [
                self::tag($s->datum),
                $s->uhrzeit ? substr($s->uhrzeit, 0, 5) : '',
                $label[$s->mannschaft_id] . ' ' . ($s->heim_eigen
                    ? Yii::t('WochenblickModule.base', 'gegen {gegner}', ['gegner' => $gegner])
                    : Yii::t('WochenblickModule.base', 'bei {gegner}', ['gegner' => $gegner])),
                ($z = $s->getZustand()) !== null ? $z : '',
            ];
        }
        return $out;
    }

    /** Wetter Samstag und Sonntag (DWD-MOSMIX über Bright Sky, Punkt Uffenheim): [[tag, symbol, text, min, max, regen mm]] */
    private static function wetter(string $samstag): array
    {
        $daten = Yii::$app->cache->getOrSet('wochenblick-wetter-' . $samstag, function () use ($samstag) {
            $url = 'https://api.brightsky.dev/weather?lat=49.5447&lon=10.2333&tz=Europe/Berlin&date=' . $samstag
                . '&last_date=' . date('Y-m-d', strtotime($samstag . ' +2 days'));
            $ch = curl_init($url);
            curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 8, CURLOPT_USERAGENT => 'rundumuffni.de']);
            $json = json_decode((string)curl_exec($ch), true);
            $ok = curl_getinfo($ch, CURLINFO_HTTP_CODE) === 200;
            curl_close($ch);
            return $ok && !empty($json['weather']) ? $json['weather'] : false;
        }, 3600);
        if ($daten === false) {
            Yii::$app->cache->delete('wochenblick-wetter-' . $samstag);
            return [];
        }
        $zone = new \DateTimeZone('Europe/Berlin');
        $tage = [];
        foreach ($daten as $w) {
            if (!isset($w['timestamp'], $w['temperature'])) {
                continue;
            }
            $t = (new \DateTimeImmutable($w['timestamp']))->setTimezone($zone);
            $tage[$t->format('Y-m-d')][(int)$t->format('G')] = $w;
        }
        $out = [];
        foreach ([$samstag, date('Y-m-d', strtotime($samstag . ' +1 day'))] as $tag) {
            $h = $tage[$tag] ?? [];
            if (count($h) < 12) {
                continue;
            }
            $temp = array_column($h, 'temperature');
            $regen = round(array_sum(array_map(fn($w) => (float)($w['precipitation'] ?? 0), $h)), 1);
            // Symbol: häufigstes am Tag (8–19 Uhr)
            $icons = array_count_values(array_map(fn($w) => (string)($w['icon'] ?? 'cloudy'),
                array_filter($h, fn($k) => $k >= 8 && $k <= 19, ARRAY_FILTER_USE_KEY)));
            arsort($icons);
            $icon = (string)array_key_first($icons);
            [$symbol, $text] = self::WETTER[$icon] ?? self::WETTER['cloudy'];
            $out[] = [self::tag($tag), $symbol, Yii::t('WochenblickModule.base', $text), (int)round(min($temp)), (int)round(max($temp)), $regen];
        }
        return $out;
    }

    private const WETTER = [
        'clear-day' => ['☀️', 'Sonnig'], 'clear-night' => ['☀️', 'Sonnig'],
        'partly-cloudy-day' => ['⛅', 'Wechselnd bewölkt'], 'partly-cloudy-night' => ['⛅', 'Wechselnd bewölkt'],
        'cloudy' => ['☁️', 'Bewölkt'], 'fog' => ['🌫️', 'Nebel'], 'wind' => ['💨', 'Windig'],
        'rain' => ['🌧️', 'Regen'], 'sleet' => ['🌨️', 'Schneeregen'], 'snow' => ['❄️', 'Schnee'],
        'hail' => ['🌨️', 'Hagel'], 'thunderstorm' => ['⛈️', 'Gewitter'],
    ];

    /** Mitteilungsblatt, wenn in den letzten 7 Tagen eine Ausgabe erschienen ist: [titel, datum, url] */
    private static function mitteilungsblatt(int $jetzt): ?array
    {
        if (!Yii::$app->hasModule('mitteilungsblatt') || !class_exists(\humhub\modules\mitteilungsblatt\models\Ausgabe::class)) {
            return null;
        }
        $a = \humhub\modules\mitteilungsblatt\models\Ausgabe::find()
            ->where(['between', 'datum', date('Y-m-d', strtotime('-6 days', $jetzt)), date('Y-m-d', $jetzt)])
            ->orderBy(['datum' => SORT_DESC])->one();
        return $a ? [$a->getTitelAnzeige(), $a->getDatumAnzeige(), Url::to(['/mitteilungsblatt/index'], true)] : null;
    }

    /** Gutscheine, die in den letzten 7 Tagen dazugekommen sind und laufen: [[titel, betrieb, gültig bis, url]] */
    private static function gutscheine(int $jetzt): array
    {
        if (!Yii::$app->hasModule('gewerbe') || Yii::$app->db->getTableSchema('gewerbe_gutschein') === null) {
            return [];
        }
        $g = \humhub\modules\gewerbe\models\Gutschein::class;
        $out = [];
        foreach ($g::sichtbar()->andWhere(['gewerbe_gutschein.status' => $g::STATUS_AKTIV])
                     ->andWhere(['>=', 'gewerbe_gutschein.created_at', date('Y-m-d H:i:s', strtotime('-7 days', $jetzt))])
                     ->orderBy(['gueltig_bis' => SORT_ASC])->with('betrieb')->all() as $x) {
            if ($x->istAufgebraucht()) {
                continue;
            }
            $out[] = [$x->titel, $x->betrieb->name ?? '', Yii::$app->formatter->asDate($x->gueltig_bis, 'php:j.n.'), $x->getUrl(true)];
        }
        return $out;
    }

    /** Tag kurz wie „Sa, 7.11.“ (andere Sprachen über den Formatter) */
    public static function tag(string $ymd): string
    {
        if (!str_starts_with(Yii::$app->language, 'de')) {
            return Yii::$app->formatter->asDate($ymd, 'EEE, d.M.');
        }
        return ['So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa'][(int)date('w', strtotime($ymd))] . ', ' . date('j.n.', strtotime($ymd));
    }

    /** Kurztext für Push und Glocke, z. B. „Wochenende: 4 Termine, 3 Spiele · Abfuhr: Mo Restmüll · Sa 12°, So 9°“ */
    public static function kurz(array $d): string
    {
        $teile = [];
        $we = count(array_filter($d['termine'], fn($t) => $t[5]));
        $woche = count($d['termine']) + $d['mehrTermine'];
        if ($woche) {
            $teile[] = Yii::t('WochenblickModule.base', '{n, plural, =1{1 Termin} other{# Termine}}', ['n' => $woche])
                . ($we ? ' ' . Yii::t('WochenblickModule.base', '({n} am Wochenende)', ['n' => $we]) : '');
        }
        if ($d['spiele']) {
            $teile[] = Yii::t('WochenblickModule.base', '{n, plural, =1{1 Spiel} other{# Spiele}}', ['n' => count($d['spiele'])]);
        }
        if ($d['abfuhr']) {
            $teile[] = Yii::t('WochenblickModule.base', 'Abfuhr: {liste}', ['liste' => implode(', ', array_map(fn($a) => $a[0] . ' ' . $a[1], $d['abfuhr']))]);
        }
        if ($d['wetter']) {
            $teile[] = implode(', ', array_map(fn($w) => explode(',', $w[0])[0] . ' ' . $w[1] . ' ' . $w[4] . '°', $d['wetter']));
        }
        if ($d['mb']) {
            $teile[] = Yii::t('WochenblickModule.base', 'Neues Mitteilungsblatt');
        }
        return $teile ? implode(' · ', $teile) : Yii::t('WochenblickModule.base', 'Dein Überblick für Wochenende und Woche');
    }

    /** Seite mit dem Überblick (Link aus Push und Mail) */
    public static function url(): string
    {
        return Url::to(['/wochenblick/ansicht'], true);
    }

    /**
     * Überblick an ein Konto: Benachrichtigung (Glocke + Push) und auf Wunsch E-Mail.
     * Texte in der Sprache des Empfängers, danach die bisherige Sprache wiederherstellen.
     */
    public static function senden(User $user, ?int $jetzt = null, ?bool $mail = null): bool
    {
        $alt = [Yii::$app->language, Yii::$app->formatter->locale, Yii::$app->formatter->timeZone];
        Yii::$app->i18n->setUserLocale($user);
        try {
            $d = self::daten($user, $jetzt);
            $kurz = self::kurz($d);
            $mail ??= self::settings($user)[1];
            $ok = true;
            if ($mail && $user->email) {
                $betreff = Yii::t('WochenblickModule.base', 'Dein Wochenüberblick {von} – {bis}', ['von' => self::tag($d['von']), 'bis' => self::tag($d['bis'])]);
                $inhalt = Yii::$app->view->renderFile('@wochenblick/views/mail/inhalt.php', ['d' => $d, 'mail' => true]);
                $text = Yii::$app->view->renderFile('@wochenblick/views/mail/text.php', ['d' => $d]);
                $mailer = Yii::$app->mailer;
                $html = $mailer->render('@humhub/views/mail/TextOnly', ['message' => $inhalt], $mailer->htmlLayout);
                $m = $mailer->compose()->setTo($user->email)->setSubject($betreff);
                $m->getSymfonyEmail()->text($text)->html($html);
                $ok = $m->send();
                if (!$ok) {
                    Yii::warning("Wochenüberblick-Mail an Nutzer {$user->id} nicht versendet", 'wochenblick');
                }
            }
        } finally {
            [Yii::$app->language, Yii::$app->formatter->locale, Yii::$app->formatter->timeZone] = $alt;
        }
        // Text der Glocke/Push entsteht beim Zustellen neu in der Sprache des Empfängers – Daten mitgeben
        Wochenueberblick::instance()->payload(['kurz' => $kurz, 'von' => $d['von']])->send($user);
        return $ok;
    }

    /**
     * Fällige Überblicke versenden (alle 15 Minuten per Crontab): am Versandtag ab der Stunde (bis 3 Stunden danach),
     * nur freigegebene Konten mit eingeschaltetem Überblick, je Woche einmal.
     * @return int Anzahl verschickter Überblicke
     */
    public static function run(?int $jetzt = null, bool $dryRun = false): int
    {
        $jetzt ??= time();
        $m = self::module();
        $stunde = (int)date('G', $jetzt);
        if ((int)date('N', $jetzt) !== $m->wochentag || $stunde < $m->stunde || $stunde >= $m->stunde + 3) {
            return 0;
        }
        $heute = date('Y-m-d', $jetzt);
        $ids = (new \yii\db\Query())->select('c.pk')->from('contentcontainer_setting s')
            ->innerJoin('contentcontainer c', 'c.id = s.contentcontainer_id AND c.class = :cls', [':cls' => User::class])
            ->where(['s.module_id' => 'wochenblick', 's.name' => 'aktiv', 's.value' => '1'])->column();
        $n = 0;
        foreach (User::find()->where(['id' => $ids, 'status' => User::STATUS_ENABLED])->all() as $user) {
            try {
                $s = $m->settings->user($user);
                if (!$m->istFreigegeben($user) || $s->get('gesendet') === $heute) {
                    continue;
                }
                if (!$dryRun) {
                    // Erst merken, dann senden: bricht der Versand ab, keine Wiederholung alle 15 Minuten
                    $s->set('gesendet', $heute);
                    self::senden($user, $jetzt);
                }
                Yii::info("Wochenüberblick an Nutzer {$user->id}", 'wochenblick');
                $n++;
            } catch (\Throwable $e) {
                Yii::error("Wochenüberblick an Nutzer {$user->id} fehlgeschlagen: $e", 'wochenblick');
            }
        }
        return $n;
    }

    /** Für die Ansichten: Link-HTML mit Inline-Stil (Mail) */
    public static function link(string $text, string $url): string
    {
        return Html::a(Html::encode($text), $url, ['style' => 'color:#446e88;text-decoration:underline']);
    }
}
