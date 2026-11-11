<?php

namespace humhub\modules\statistik\components;

use humhub\helpers\DeviceDetectorHelper;
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
        . 'facebookexternalhit|whatsapp|telegram|discord|scrapy|http-?client|okhttp|axios|libwww|feed|rss|php\/|perl|ruby|'
        // Eigene Tests (Playwright, curl usw.) hängen „UffiTest“ an die Browserkennung an
        . 'uffitest/i';

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
        // Weder Browser noch Betriebssystem erkennbar: Programm (Link-Vorschau, Überwachung, Feed-Leser), kein Mensch
        if (self::browser($ua) === 'Andere' && self::system($ua) === 'Andere') {
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

        $db = $app->db;
        if (!$user->isGuest) {
            // Wer sich heute erst als Gast umgesehen und dann angemeldet hat, ist derselbe Besucher:
            // Gast-Aufrufe mit gleicher IP und Browserkennung dem Konto zuordnen (sonst zählt die Person doppelt)
            $gast = substr(hash('sha256', self::salz($tag) . '|' . $req->userIP . '|' . $ua), 0, 16);
            $db->createCommand()->update('statistik_aufruf', ['besucher' => $besucher], ['besucher' => $gast, 'tag' => $tag])->execute();
        }
        // Massenabruf eines Gasts (Programm mit Browserkennung): nicht zählen und seine Aufrufe des Tages löschen
        if ($user->isGuest) {
            $sperre = Yii::getAlias('@runtime/statistik') . "/massen-$tag";
            if (is_file($sperre) && in_array($besucher, file($sperre, FILE_IGNORE_NEW_LINES) ?: [], true)) {
                return;
            }
            $kurz = (int)$db->createCommand('SELECT COUNT(*) FROM statistik_aufruf WHERE besucher = :b AND tag = :t AND zeit >= :z',
                [':b' => $besucher, ':t' => $tag, ':z' => date('Y-m-d H:i:s', $jetzt - $modul->massenMinuten * 60)])->queryScalar();
            if ($kurz >= $modul->massenAufrufe) {
                // Für den Rest des Tages merken (Datei neben dem Tages-Salz, wird mit ihm gelöscht)
                file_put_contents($sperre, $besucher . "\n", FILE_APPEND | LOCK_EX);
                // Einmal festhalten, wer das war (Sicherheit, Art. 6 Abs. 1 lit. f DSGVO; Log-Tabelle wird regelmäßig geleert)
                $pfade = $db->createCommand('SELECT pfad FROM statistik_aufruf WHERE besucher = :b AND tag = :t ORDER BY id LIMIT 15',
                    [':b' => $besucher, ':t' => $tag])->queryColumn();
                Yii::warning('Massenabruf erkannt: ' . $kurz . ' Aufrufe in ' . $modul->massenMinuten . ' Min. von IP ' . $req->userIP
                    . ', Browserkennung „' . $ua . '“, zuletzt ' . self::pfad() . '; erste Seiten: ' . implode(', ', $pfade), 'statistik-massenabruf');
                $db->createCommand()->delete('statistik_aufruf', ['besucher' => $besucher, 'tag' => $tag])->execute();
                return;
            }
        }

        // Besuch fortsetzen, wenn der letzte Aufruf weniger als 30 Minuten zurückliegt
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
            // App an ihren Kopfzeilen erkennen (wie HumHub selbst), die Browserkennung enthält „HumHub“ nicht
            'geraet' => DeviceDetectorHelper::isAppRequest() ? 'App' : self::geraet($ua),
            'browser' => self::browser($ua),
            'system' => self::system($ua),
            'herkunft' => $neu ? self::herkunft() : null,
            // Start über das Symbol auf dem Startbildschirm (start_url im Manifest, siehe Events::onManifest)
            'start' => $req->get('quelle') === Module::START_QUELLE ? 1 : 0,
        ])->execute();
        if (!$user->isGuest) {
            // Aktive Konten je Tag (für täglich/wöchentlich/monatlich aktive Konten): nur Konto-Nr. und Datum
            $db->createCommand('INSERT IGNORE INTO statistik_konto_tag (tag, user_id) VALUES (:t, :u)',
                [':t' => $tag, ':u' => $user->id])->execute();
        }
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
                foreach (array_merge(glob("$dir/salz-*") ?: [], glob("$dir/massen-*") ?: []) as $alt) {
                    if ($alt !== $datei && $alt !== "$dir/massen-$tag") {
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
        // Nachrichten: keine Gesprächsnummer speichern
        if (is_scalar($id) && !str_starts_with($pfad, '/mail/') && preg_match('/^\d{1,10}$/', (string)$id)) {
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
