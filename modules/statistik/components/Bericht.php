<?php

namespace humhub\modules\statistik\components;

use humhub\modules\content\models\Content;
use humhub\modules\space\models\Space;
use Yii;
use yii\db\Query;

/** Auswertung der Seitenaufrufe für einen Zeitraum [von, bis] (jeweils ganze Tage) */
class Bericht
{
    public const ZEITRAEUME = ['heute', 'gestern', '7', '30', '90', '365'];

    public string $von;
    public string $bis;

    public function __construct(string $von, string $bis)
    {
        $this->von = $von;
        $this->bis = $bis;
    }

    public static function fuer(string $zeitraum, ?string $von = null, ?string $bis = null): self
    {
        $heute = date('Y-m-d');
        if ($von && $bis && preg_match('/^\d{4}-\d\d-\d\d$/', $von) && preg_match('/^\d{4}-\d\d-\d\d$/', $bis)) {
            return $von <= $bis ? new self($von, min($bis, $heute)) : new self($bis, $von);
        }
        return match ($zeitraum) {
            'heute' => new self($heute, $heute),
            'gestern' => new self(date('Y-m-d', strtotime('-1 day')), date('Y-m-d', strtotime('-1 day'))),
            default => new self(date('Y-m-d', strtotime('-' . (((int)$zeitraum ?: 30) - 1) . ' days')), $heute),
        };
    }

    public function tage(): int
    {
        return (int)round((strtotime($this->bis) - strtotime($this->von)) / 86400) + 1;
    }

    /** Gleich langer Zeitraum direkt davor, für den Vergleich */
    public function vorher(): self
    {
        $t = $this->tage();
        return new self(date('Y-m-d', strtotime("$this->von -$t days")), date('Y-m-d', strtotime("$this->von -1 day")));
    }

    private function q(): Query
    {
        return (new Query())->from('statistik_aufruf')->where(['between', 'tag', $this->von, $this->bis]);
    }

    /** Kennzahlen: Aufrufe, Besucher, Besuche, Absprungrate, Seiten je Besuch, angemeldete Besucher */
    public function kennzahlen(): array
    {
        $k = $this->q()->select([
            'aufrufe' => 'COUNT(*)',
            'besucher' => 'COUNT(DISTINCT besucher)',
            'besuche' => 'COUNT(DISTINCT besuch)',
            'angemeldet' => 'COUNT(DISTINCT CASE WHEN angemeldet = 1 THEN besucher END)',
        ])->one();
        $k = array_map('intval', $k);
        $einzel = (int)(new Query())->from(['b' => $this->q()->select('besuch')->groupBy('besuch')->having('COUNT(*) = 1')])->count();
        $k['absprung'] = $k['besuche'] ? round($einzel / $k['besuche'] * 100) : 0;
        $k['seitenJeBesuch'] = $k['besuche'] ? round($k['aufrufe'] / $k['besuche'], 1) : 0;
        return $k;
    }

    /** Konten aus HumHub selbst (keine zusätzliche Erfassung): zuletzt angemeldet bzw. neu registriert im Zeitraum */
    public function konten(): array
    {
        $von = "$this->von 00:00:00";
        $bis = "$this->bis 23:59:59";
        return [
            'aktiv' => (int)(new Query())->from('user')->where(['status' => 1])->andWhere(['between', 'last_login', $von, $bis])->count(),
            'neu' => (int)(new Query())->from('user')->where(['between', 'created_at', $von, $bis])->count(),
            'gesamt' => (int)(new Query())->from('user')->where(['status' => 1])->count(),
        ];
    }

    /** Wer in den letzten 5 Minuten eine Seite aufgerufen hat */
    public static function jetztAktiv(): int
    {
        return (int)(new Query())->from('statistik_aufruf')
            ->where(['>=', 'zeit', date('Y-m-d H:i:s', time() - 300)])->count('DISTINCT besucher');
    }

    /**
     * Verlauf: je Stunde (ein Tag), je Tag (bis 92 Tage) oder je Monat.
     * @return array[] [['label' => …, 'kurz' => …, 'aufrufe' => n, 'besucher' => n], …] lückenlos
     */
    public function verlauf(): array
    {
        $tage = $this->tage();
        if ($tage === 1) {
            $rows = $this->q()->select(['k' => 'HOUR(zeit)', 'aufrufe' => 'COUNT(*)', 'besucher' => 'COUNT(DISTINCT besucher)'])
                ->groupBy('k')->indexBy('k')->all();
            $schritte = [];
            for ($h = 0; $h < 24; $h++) {
                $schritte[$h] = [sprintf('%02d:00–%02d:59 Uhr', $h, $h), sprintf('%02d', $h)];
            }
        } elseif ($tage <= 92) {
            $rows = $this->q()->select(['k' => 'tag', 'aufrufe' => 'COUNT(*)', 'besucher' => 'COUNT(DISTINCT besucher)'])
                ->groupBy('k')->indexBy('k')->all();
            $schritte = [];
            $wt = ['So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa'];
            for ($t = strtotime($this->von); $t <= strtotime($this->bis); $t = strtotime('+1 day', $t)) {
                $schritte[date('Y-m-d', $t)] = [$wt[date('w', $t)] . ', ' . date('d.m.Y', $t), date('d.m.', $t)];
            }
        } else {
            // Besucher je Monat = Summe der Tagesbesucher (Tages-Salz)
            $rows = (new Query())->from(['d' => $this->q()->select(['m' => "DATE_FORMAT(tag, '%Y-%m')", 'tag',
                'a' => 'COUNT(*)', 'b' => 'COUNT(DISTINCT besucher)'])->groupBy('tag')])
                ->select(['k' => 'm', 'aufrufe' => 'SUM(a)', 'besucher' => 'SUM(b)'])->groupBy('k')->indexBy('k')->all();
            $schritte = [];
            $monate = ['Jan', 'Feb', 'Mär', 'Apr', 'Mai', 'Jun', 'Jul', 'Aug', 'Sep', 'Okt', 'Nov', 'Dez'];
            for ($t = strtotime(substr($this->von, 0, 7) . '-01'); $t <= strtotime($this->bis); $t = strtotime('+1 month', $t)) {
                $schritte[date('Y-m', $t)] = [$monate[date('n', $t) - 1] . ' ' . date('Y', $t), $monate[date('n', $t) - 1]];
            }
        }
        $out = [];
        foreach ($schritte as $k => [$label, $kurz]) {
            $out[] = ['label' => $label, 'kurz' => $kurz,
                'aufrufe' => (int)($rows[$k]['aufrufe'] ?? 0), 'besucher' => (int)($rows[$k]['besucher'] ?? 0)];
        }
        return $out;
    }

    /** Meistbesuchte Seiten: [pfad, aufrufe, besucher] */
    public function seiten(int $limit = 25, bool $nurEinstieg = false): array
    {
        $q = $this->q()->select(['pfad', 'aufrufe' => 'COUNT(*)', 'besucher' => 'COUNT(DISTINCT besucher)'])
            ->groupBy('pfad')->orderBy(['aufrufe' => SORT_DESC])->limit($limit);
        if ($nurEinstieg) {
            $q->andWhere(['neuer_besuch' => 1]);
        }
        return $q->all();
    }

    /** Aufrufe je Bereich (Space bzw. Hauptseite) */
    public function bereiche(): array
    {
        $rows = $this->q()->select([
            'b' => "CASE WHEN pfad LIKE '/s/%' THEN SUBSTRING_INDEX(SUBSTRING_INDEX(SUBSTRING(pfad, 4), '/', 1), '?', 1) ELSE '' END",
            'aufrufe' => 'COUNT(*)', 'besucher' => 'COUNT(DISTINCT besucher)',
        ])->groupBy('b')->orderBy(['aufrufe' => SORT_DESC])->all();
        $namen = Space::find()->select(['name', 'url'])->where(['url' => array_column($rows, 'b')])->indexBy('url')->column();
        foreach ($rows as &$r) {
            $r['name'] = $r['b'] === '' ? Yii::t('StatistikModule.base', 'Allgemeine Seiten (ohne Space)') : ($namen[$r['b']] ?? $r['b']);
            $r['url'] = $r['b'] === '' ? null : '/s/' . $r['b'];
        }
        return $rows;
    }

    /** Woher Besuche kommen (nur fremde Domains); „direkt“ = ohne verweisende Seite */
    public function herkunft(int $limit = 15): array
    {
        return $this->q()->andWhere(['neuer_besuch' => 1])
            ->select(['name' => "COALESCE(herkunft, '')", 'anzahl' => 'COUNT(*)'])
            ->groupBy('name')->orderBy(['anzahl' => SORT_DESC])->limit($limit)->all();
    }

    /** Besucher nach Gerät/Browser/System (Spalte), absteigend */
    public function verteilung(string $spalte): array
    {
        return $this->q()->select(['name' => $spalte, 'anzahl' => 'COUNT(DISTINCT besucher)'])
            ->groupBy('name')->orderBy(['anzahl' => SORT_DESC])->all();
    }

    /** Aufrufe nach Uhrzeit (0–23) bzw. Wochentag (Mo–So) */
    public function zeitmuster(): array
    {
        $std = $this->q()->select(['k' => 'HOUR(zeit)', 'n' => 'COUNT(*)'])->groupBy('k')->indexBy('k')->column();
        // WEEKDAY: 0 = Montag
        $wt = $this->q()->select(['k' => 'WEEKDAY(zeit)', 'n' => 'COUNT(*)'])->groupBy('k')->indexBy('k')->column();
        $stunden = [];
        for ($h = 0; $h < 24; $h++) {
            $stunden[] = ['label' => sprintf('%02d', $h), 'titel' => sprintf('%02d:00–%02d:59 Uhr', $h, $h), 'n' => (int)($std[$h] ?? 0)];
        }
        $tage = [];
        foreach (['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'] as $i => $n) {
            $tage[] = ['label' => $n, 'titel' => $n, 'n' => (int)($wt[$i] ?? 0)];
        }
        return [$stunden, $tage];
    }

    /** Lesbarer Name für einen gespeicherten Pfad */
    public static function bezeichnung(string $pfad): string
    {
        static $spaces = null;
        $spaces ??= Space::find()->select(['name', 'url'])->indexBy('url')->column();
        [$p, $abfrage] = array_pad(explode('?', $pfad, 2), 2, '');
        $id = str_starts_with($abfrage, 'id=') ? (int)substr($abfrage, 3) : null;
        $t = fn($s) => Yii::t('StatistikModule.base', $s);

        $fest = [
            '/' => $t('Startseite'), '/dashboard' => $t('Startseite'), '/dashboard/dashboard' => $t('Startseite'),
            '/user/auth/login' => $t('Anmeldung'), '/user/registration' => $t('Registrierung'),
            '/spaces' => $t('Space-Verzeichnis'), '/people' => $t('Personen'), '/u/*' => $t('Profilseiten (zusammengefasst)'),
            '/calendar/global' => $t('Kalender'), '/search' => $t('Suche'), '/mail/mail/index' => $t('Nachrichten'),
            '/notification/overview' => $t('Benachrichtigungen'), '/ki-assistent' => 'Uffi',
        ];
        if (isset($fest[$p])) {
            return $fest[$p];
        }
        if (str_starts_with($p, '/u/*/')) {
            return $t('Profilseiten (zusammengefasst)') . ' › ' . substr($p, 5);
        }
        if ($p === '/content/perma' && $id) {
            $c = Content::findOne($id);
            if ($c && ($rec = $c->getPolymorphicRelation())) {
                return $c->getContentName() . ': ' . mb_strimwidth(trim(strip_tags((string)$rec->getContentDescription())), 0, 60, '…');
            }
        }
        if (preg_match('#^/s/([^/]+)/?(.*)$#', $p, $m)) {
            $name = $spaces[$m[1]] ?? $m[1];
            $rest = trim(preg_replace('#/(index|view)$#', '', '/' . $m[2]), '/');
            $rest = preg_replace('#^(space/space|home)$#', '', $rest);
            return $name . ($rest !== '' ? ' › ' . $rest : '') . ($id ? " #$id" : '');
        }
        return $pfad;
    }
}
