<?php

namespace humhub\modules\statistik\components;

use humhub\modules\statistik\Module;
use Yii;
use yii\web\Response;

/**
 * Cookielose Reichweitenmessung nach dem Vorbild von Plausible/Matomo („cookieless“):
 * - nichts wird im Browser gespeichert oder ausgelesen (§ 25 TDDDG greift nicht, keine Einwilligung nötig),
 * - IP-Adresse und Browserkennung gehen nur in einen Hash mit Tages-Salz ein und werden nicht gespeichert,
 * - das Salz wird jeden Tag neu gewürfelt und das alte gelöscht (nur auf dem Server, nicht in Datenbank/Sicherung) → Besucher sind nur innerhalb
 *   eines Tages wiedererkennbar, danach ist der Hash keiner Person mehr zuzuordnen,
 * - Bots, Admins (einstellbar) und Browser mit DNT/GPC werden nicht gezählt.
 */
class Erfassung
{
    private const BOTS = '/bot|crawl|spider|slurp|curl|wget|python|java\/|go-http|headless|lighthouse|preview|monitor|uptime|'
        . 'facebookexternalhit|whatsapp|telegram|discord|scrapy|http-?client|okhttp|axios|libwww|feed|rss|php\/|perl|ruby/i';

    public static function erfassen(): void
    {
        $app = Yii::$app;
        $req = $app->request;
        $res = $app->response;
        /** @var Module $modul */
        $modul = $app->getModule('statistik');

        if (!$req->isGet || $res->statusCode !== 200 || $res->format !== Response::FORMAT_HTML) {
            return;
        }
        // Nachgeladene Teile (Stream, Fenster) zählen nicht, Seitenwechsel per Pjax schon
        if ($req->isAjax && !$req->isPjax) {
            return;
        }
        if (in_array($app->requestedRoute, $modul->ausgeschlosseneRouten, true)) {
            return;
        }
        $h = $req->headers;
        if (in_array('prefetch', [$h->get('Purpose'), $h->get('Sec-Purpose'), $h->get('X-Moz')], true)) {
            return;
        }
        if ($modul->dntBeachten && ($h->get('DNT') === '1' || $h->get('Sec-GPC') === '1')) {
            return;
        }
        $ua = (string)$req->userAgent;
        if ($ua === '' || preg_match(self::BOTS, $ua)) {
            return;
        }
        $user = $app->user;
        if (!$modul->adminsZaehlen && !$user->isGuest && $user->isAdmin()) {
            return;
        }

        $jetzt = time();
        $tag = date('Y-m-d', $jetzt);
        $besucher = substr(hash('sha256', self::salz($tag) . '|'
            . ($user->isGuest ? $req->userIP . '|' . $ua : 'konto|' . $user->id)), 0, 16);

        // Besuch fortsetzen, wenn der letzte Aufruf weniger als 30 Minuten zurückliegt
        $db = $app->db;
        $letzter = $db->createCommand('SELECT besuch, zeit FROM statistik_aufruf WHERE besucher = :b AND tag = :t ORDER BY id DESC LIMIT 1',
            [':b' => $besucher, ':t' => $tag])->queryOne();
        $neu = !$letzter || strtotime($letzter['zeit']) < $jetzt - $modul->besuchPauseMinuten * 60;

        $db->createCommand()->insert('statistik_aufruf', [
            'zeit' => date('Y-m-d H:i:s', $jetzt),
            'tag' => $tag,
            'pfad' => self::pfad(),
            'besucher' => $besucher,
            'besuch' => $neu ? bin2hex(random_bytes(8)) : $letzter['besuch'],
            'neuer_besuch' => $neu ? 1 : 0,
            'angemeldet' => $user->isGuest ? 0 : 1,
            'geraet' => self::geraet($ua),
            'browser' => self::browser($ua),
            'system' => self::system($ua),
            'herkunft' => $neu ? self::herkunft() : null,
        ])->execute();
    }

    /**
     * Tages-Salz: zufällig, je Tag eine Datei in @runtime des laufenden Containers – nicht in der Datenbank und damit
     * nicht in den Sicherungen (sonst ließen sich Konto-ID bzw. IP-Adresse mit dem Salz des Tages zurückrechnen) und
     * nicht im Cache (cache/flush-all läuft stündlich). Dateien früherer Tage werden gelöscht.
     */
    private static function salz(string $tag): string
    {
        $dir = Yii::getAlias('@runtime/statistik');
        $datei = "$dir/salz-$tag";
        if (!is_file($datei)) {
            if (!is_dir($dir)) {
                @mkdir($dir, 0700, true);
            }
            // „x“: nur eine Anfrage legt die Datei an, gleichzeitige lesen danach dieselbe
            if (($f = @fopen($datei, 'x')) !== false) {
                fwrite($f, bin2hex(random_bytes(32)));
                fclose($f);
                chmod($datei, 0600);
                foreach (glob("$dir/salz-*") ?: [] as $alt) {
                    if ($alt !== $datei) {
                        @unlink($alt);
                    }
                }
            }
        }
        // Gerade angelegt, aber noch leer (gleichzeitige Anfrage): kurz warten
        for ($i = 0; ($salz = (string)@file_get_contents($datei)) === '' && $i < 10; $i++) {
            usleep(10000);
        }
        if ($salz === '') {
            throw new \RuntimeException('Statistik: Tages-Salz nicht lesbar');
        }
        return $salz;
    }

    /** Pfad ohne Abfrage, nur „id“ bleibt (Beiträge, Termine); Profile werden zusammengefasst */
    public static function pfad(): string
    {
        $pfad = '/' . trim(Yii::$app->request->pathInfo, '/');
        if (preg_match('#^/u/[^/]+#', $pfad)) {
            $pfad = preg_replace('#^/u/[^/]+#', '/u/*', $pfad);
        }
        $id = Yii::$app->request->get('id');
        if (is_scalar($id) && preg_match('/^\d{1,10}$/', (string)$id)) {
            $pfad .= '?id=' . $id;
        }
        return mb_substr($pfad, 0, 255);
    }

    /** Nur die Domain einer fremden verweisenden Seite */
    private static function herkunft(): ?string
    {
        $ref = (string)Yii::$app->request->referrer;
        $host = strtolower((string)parse_url($ref, PHP_URL_HOST));
        if ($host === '' && str_starts_with($ref, 'android-app://')) {
            $host = substr($ref, 14);
        }
        $host = preg_replace('/^(www|m|l|lm)\./', '', trim($host, '/'));
        if ($host === '' || $host === preg_replace('/^www\./', '', strtolower(Yii::$app->request->hostName))) {
            return null;
        }
        return mb_substr($host, 0, 100);
    }

    public static function geraet(string $ua): string
    {
        if (stripos($ua, 'humhub') !== false) {
            return 'App';
        }
        if (preg_match('/ipad|tablet|kindle|silk|android(?!.*mobile)/i', $ua)) {
            return 'Tablet';
        }
        return preg_match('/mobi|iphone|ipod|android/i', $ua) ? 'Handy' : 'PC';
    }

    public static function browser(string $ua): string
    {
        foreach (['Edg/' => 'Edge', 'OPR/' => 'Opera', 'SamsungBrowser' => 'Samsung Internet', 'Firefox/' => 'Firefox',
                     'FxiOS' => 'Firefox', 'CriOS' => 'Chrome', 'Chrome/' => 'Chrome', 'Safari/' => 'Safari'] as $kenn => $name) {
            if (str_contains($ua, $kenn)) {
                return $name;
            }
        }
        return 'Andere';
    }

    public static function system(string $ua): string
    {
        foreach (['/android/i' => 'Android', '/iphone|ipad|ipod/i' => 'iOS', '/windows/i' => 'Windows',
                     '/mac os x|macintosh/i' => 'macOS', '/cros/i' => 'ChromeOS', '/linux/i' => 'Linux'] as $muster => $name) {
            if (preg_match($muster, $ua)) {
                return $name;
            }
        }
        return 'Andere';
    }
}
