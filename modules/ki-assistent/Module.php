<?php

namespace humhub\modules\kiAssistent;

class Module extends \humhub\components\Module
{
    /** Obergrenze an Anfragen pro Kalendermonat, damit das Claude-Guthaben nicht unbemerkt aufgebraucht wird */
    public int $monthlyLimit = 1000;

    /** Davon höchstens so viele für Gäste (ohne Konto), damit Missbrauch Uffi nicht für alle Angemeldeten abschaltet */
    public int $guestMonthlyLimit = 400;

    /** Kostenbremse je Monat: Eingabe-Tokens + 5 × Ausgabe-Tokens (Ausgabe kostet ein Vielfaches) */
    public int $monthlyTokenLimit = 60000000;

    /** Obergrenze je Konto bzw. (bei Gästen) je Anschluss und Stunde bzw. Tag, gegen Missbrauch der offenen Nutzung */
    public int $hourlyLimit = 20;
    public int $dailyLimit = 60;

    /** Bewertungen 👍/👎 je Konto bzw. Anschluss und Stunde */
    public int $feedbackHourlyLimit = 10;

    /**
     * Links in Uffis Antworten sind nur zu diesen Seiten klickbar (Endung der Domain); alle anderen Adressen
     * erscheinen als Text. Schützt davor, dass eingeschleuste Texte (Beiträge, Meldungen) Phishing-Links empfehlen.
     * Dazu kommen die Websites der Vereine aus dem Vereinsverzeichnis.
     */
    public array $linkDomains = [
        'rundumuffni.de', 'uffenheim.de', 'stadtwerke-uffenheim.de', 'buergerliste-uffenheim.de',
        'bayern.de', 'bund.de', 'gov.de', 'bayernportal.de', 'komuna.net', 'kreis-nea.de', 'terminland.de',
        'personalausweisportal.de', 'bundesjustizamt.de', 'bundespolizei.de', 'auswaertiges-amt.de', 'personenstandsrecht.de',
        'datenschutz-bayern.de', 'digitale-verwaltung.de', 'buergerdienste-online.de', 'little-bird.de', 'vvm-info.de',
        'blak.de', 'notdienst-portal.de', 'aponet.de', '116117.de', 'zahnarzt-notdienst.de', 'diakonie-neustadt-aisch.de',
        'wikipedia.org', 'openstreetmap.org', 'dwd.de',
    ];

    /**
     * Online-Terminbuchung der Stadt (Bürgerbüro: Einwohnermeldeamt, Pass-, Standes-, Gewerbe-, Ordnungsamt, Fundsachen).
     * Die Buchung läuft bei der Stadt; wir verlinken nur. Ändert die Stadt die Adresse: hier anpassen und
     * scripts/run-setup.sh rathaus-termin erneut ausführen (Menüpunkt im Space „Rathaus Uffenheim“).
     */
    public string $terminlandUrl = 'https://www.terminland.de/uffenheim/?m=38075';

    /** Bereiche, die man bei Terminland auswählen kann */
    public array $terminlandAemter = ['Einwohnermeldeamt', 'Passamt', 'Standesamt', 'Gewerbeamt', 'Ordnungsamt', 'Fundsachen'];

    /** Claude-Modell, per CLAUDE_MODEL in compose.yml änderbar */
    public function getModel(): string
    {
        return getenv('CLAUDE_MODEL') ?: 'claude-haiku-5-5';
    }

    /** API-Schlüssel als Docker-Secret (secrets/anthropic_api_key auf dem Host) */
    public function getApiKey(): ?string
    {
        $key = @file_get_contents('/run/secrets/anthropic_api_key');
        return $key !== false && trim($key) !== '' ? trim($key) : null;
    }

    /**
     * Nur für Volljährige (Nutzungsbedingungen des KI-Anbieters): Bestätigung einmal je Konto
     * (Benutzereinstellung, wird mit dem Konto gelöscht), bei Gästen je Browser-Sitzung.
     */
    public function isAgeConfirmed(): bool
    {
        if (\Yii::$app->user->isGuest) {
            return (bool)\Yii::$app->session->get('ki-age-confirmed');
        }
        return (bool)$this->settings->user()?->get('age-confirmed');
    }

    public function confirmAge(): void
    {
        if (\Yii::$app->user->isGuest) {
            \Yii::$app->session->set('ki-age-confirmed', 1);
            return;
        }
        $this->settings->user()?->set('age-confirmed', date('Y-m-d H:i:s'));
    }

    /** Wer fragt: Konto, sonst Anschluss (IPv6 auf das /64-Netz gekürzt – ein Anschluss hat dort viele Adressen) */
    public function ident(): string
    {
        if (!\Yii::$app->user->isGuest) {
            return 'u-' . \Yii::$app->user->id;
        }
        $ip = (string)\Yii::$app->request->userIP;
        if (str_contains($ip, ':') && ($bin = @inet_pton($ip)) !== false) {
            $ip = bin2hex(substr($bin, 0, 8)) . '::/64';
        }
        return 'ip-' . $ip;
    }

    /**
     * Prüft alle Grenzen und reserviert eine Anfrage (atomar über die Datenbank-Sperre, damit parallele Anfragen
     * die Grenzen nicht überspringen). Gibt null zurück oder den Grund für die Ablehnung.
     */
    public function reserve(): ?string
    {
        $mutex = \Yii::$app->mutex;
        if (!$mutex->acquire('ki-assistent-limit', 5)) {
            return 'Uffi ist gerade sehr gefragt. Bitte versuch es gleich noch einmal.';
        }
        try {
            [$requests, $in, $out, $guests] = $this->getUsage();
            if ($requests >= $this->monthlyLimit || $in + 5 * $out >= $this->monthlyTokenLimit) {
                return 'Uffi macht für diesen Monat Pause – das Kontingent ist aufgebraucht. Ab dem 1. geht es weiter.';
            }
            $gast = \Yii::$app->user->isGuest;
            if ($gast && $guests >= $this->guestMonthlyLimit) {
                return 'Ohne Konto ist Uffis Kontingent für diesen Monat aufgebraucht. Mit einem kostenlosen Konto geht es weiter.';
            }
            $cache = \Yii::$app->cache;
            $stunde = 'ki-stunde-' . $this->ident() . '-' . date('YmdH');
            $tag = 'ki-tag-' . $this->ident() . '-' . date('Ymd');
            if ((int)$cache->get($stunde) >= $this->hourlyLimit || (int)$cache->get($tag) >= $this->dailyLimit) {
                return 'Du hast schon viele Fragen gestellt. Bitte versuch es später noch einmal.';
            }
            $cache->set($stunde, (int)$cache->get($stunde) + 1, 3600);
            $cache->set($tag, (int)$cache->get($tag) + 1, 86400);
            $this->saveUsage([$requests + 1, $in, $out, $guests + ($gast ? 1 : 0)]);
            return null;
        } finally {
            $mutex->release('ki-assistent-limit');
        }
    }

    /** Bewertungen begrenzen (je Konto bzw. Anschluss und Stunde) */
    public function takeFeedback(): bool
    {
        $key = 'ki-feedback-' . $this->ident() . '-' . date('YmdH');
        $cache = \Yii::$app->cache;
        if ((int)$cache->get($key) >= $this->feedbackHourlyLimit) {
            return false;
        }
        $cache->set($key, (int)$cache->get($key) + 1, 3600);
        return true;
    }

    /** Zähler für den laufenden Monat: [Anfragen, Eingabe-Tokens, Ausgabe-Tokens, davon Gast-Anfragen] */
    public function getUsage(): array
    {
        $usage = json_decode((string)$this->settings->get('usage-' . date('Y-m')), true);
        return array_values(array_map('intval', is_array($usage) ? $usage + [0, 0, 0, 0] : [0, 0, 0, 0]));
    }

    private function saveUsage(array $usage): void
    {
        $this->settings->set('usage-' . date('Y-m'), json_encode(array_values($usage)));
    }

    /** Tokens nach der Antwort nachtragen (die Anfrage selbst zählt schon bei reserve()) */
    public function addUsage(int $promptTokens, int $outputTokens): void
    {
        $mutex = \Yii::$app->mutex;
        $mutex->acquire('ki-assistent-limit', 5);
        try {
            [$requests, $in, $out, $guests] = $this->getUsage();
            $this->saveUsage([$requests, $in + $promptTokens, $out + $outputTokens, $guests]);
        } finally {
            $mutex->release('ki-assistent-limit');
        }
    }

    /** Hosts, deren Links im Chat klickbar sind (Domain-Endungen + Websites der Vereine und Betriebe) */
    public function getLinkDomains(): array
    {
        return \Yii::$app->cache->getOrSet('ki-link-domains', function () {
            $domains = $this->linkDomains;
            if (\Yii::$app->db->getTableSchema('verein') !== null) {
                foreach ((new \yii\db\Query())->select('website')->from('verein')->where(['not', ['website' => null]])
                             ->andWhere(['!=', 'status', 'vorgeschlagen'])->column() as $url) {
                    $host = strtolower((string)parse_url($url, PHP_URL_HOST));
                    if ($host !== '') {
                        $domains[] = preg_replace('/^www\./', '', $host);
                    }
                }
            }
            if (\Yii::$app->db->getTableSchema('gewerbe_betrieb') !== null) {
                foreach ((new \yii\db\Query())->select('website')->from('gewerbe_betrieb')->where(['not', ['website' => null]])
                             ->andWhere(['!=', 'status', 'vorgeschlagen'])->column() as $url) {
                    $host = strtolower((string)parse_url($url, PHP_URL_HOST));
                    if ($host !== '') {
                        $domains[] = preg_replace('/^www\./', '', $host);
                    }
                }
            }
            return array_values(array_unique($domains));
        }, 3600);
    }
}
