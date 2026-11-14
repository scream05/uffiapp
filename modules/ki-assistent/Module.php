<?php

namespace humhub\modules\kiAssistent;

class Module extends \humhub\components\Module
{
    /** Obergrenze an Anfragen pro Kalendermonat, damit das KI-Guthaben nicht unbemerkt aufgebraucht wird */
    public int $monthlyLimit = 1000;

    /** Davon höchstens so viele für Gäste (ohne Konto), damit Missbrauch Uffi nicht für alle Angemeldeten abschaltet */
    public int $guestMonthlyLimit = 400;

    /** Kostenbremse je Monat: Eingabe-Tokens + 5 × Ausgabe-Tokens (Ausgabe kostet ein Vielfaches) */
    public int $monthlyTokenLimit = 60000000;

    /** Obergrenze je Konto bzw. (bei Gästen) je Anschluss und Stunde bzw. Tag, gegen Missbrauch der offenen Nutzung */
    public int $hourlyLimit = 20;
    public int $dailyLimit = 60;

    /** Übersetzen auf Klick: neue Übersetzungen je Monat insgesamt und je Konto bzw. Anschluss und Stunde (Treffer aus dem Zwischenspeicher zählen nicht) */
    public int $uebersetzungMonat = 4000;
    public int $uebersetzungStunde = 60;

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

    /** Wählbare KI-Anbieter (Schlüssel → Anzeigename) */
    public const ANBIETER = [
        'claude' => 'Claude (Anthropic)',
        'bedrock' => 'Claude (Anthropic, über AWS Bedrock)',
        'openai' => 'ChatGPT (OpenAI)',
    ];

    /** EU-Regionen für Claude über AWS Bedrock */
    public const BEDROCK_REGIONEN = [
        'eu-central-1' => 'Frankfurt', 'eu-west-1' => 'Irland', 'eu-north-1' => 'Stockholm',
        'eu-west-3' => 'Paris', 'eu-south-1' => 'Mailand', 'eu-central-2' => 'Zürich',
    ];

    /**
     * KI-Anbieter: „claude“ (Anthropic direkt), „bedrock“ (Claude über AWS Bedrock) oder „openai“.
     * Die Auswahl unter Administration → KI-Anbieter hat Vorrang vor KI_ANBIETER aus compose.yml/.env.
     */
    public function getAnbieter(): string
    {
        $wahl = (string)$this->settings->get('anbieter');
        if (isset(self::ANBIETER[$wahl])) {
            return $wahl;
        }
        return isset(self::ANBIETER[getenv('KI_ANBIETER')]) ? getenv('KI_ANBIETER') : 'claude';
    }

    /** Anbieter laut compose.yml/.env (gilt, solange in der Administration nichts gewählt ist) */
    public function getAnbieterStandard(): string
    {
        return isset(self::ANBIETER[getenv('KI_ANBIETER')]) ? getenv('KI_ANBIETER') : 'claude';
    }

    /** Anzeigename des Anbieters für Hinweise („ChatGPT (OpenAI)“) */
    public function getAnbieterName(?string $anbieter = null): string
    {
        return self::ANBIETER[$anbieter ?? $this->getAnbieter()];
    }

    /** AWS-Region für Bedrock: Auswahl in der Administration, sonst BEDROCK_REGION, sonst Frankfurt */
    public function getBedrockRegion(): string
    {
        $wahl = (string)$this->settings->get('bedrock-region');
        return isset(self::BEDROCK_REGIONEN[$wahl]) ? $wahl : (getenv('BEDROCK_REGION') ?: 'eu-central-1');
    }

    /**
     * Modell des Anbieters, per CLAUDE_MODEL/OPENAI_MODEL in compose.yml änderbar; für Bedrock zusätzlich
     * in der Administration (dort sind nicht immer dieselben Modelle freigeschaltet wie bei Anthropic).
     * @param string $stufe standard (Uffi, Übersetzen, Auslesen) oder stark (lange, anspruchsvolle Texte)
     * @param string|null $claudeModell abweichendes Claude-Modell (z. B. aus der Konfiguration eines Moduls)
     */
    public function getModel(string $stufe = 'standard', ?string $claudeModell = null, ?string $anbieter = null): string
    {
        $anbieter ??= $this->getAnbieter();
        if ($anbieter === 'openai') {
            return ($stufe === 'stark' ? getenv('OPENAI_MODEL_STARK') : null) ?: getenv('OPENAI_MODEL') ?: 'gpt-5.4';
        }
        if ($anbieter === 'bedrock' && $claudeModell === null && ($m = trim((string)$this->settings->get('bedrock-modell'))) !== '') {
            return $m;
        }
        return $claudeModell ?: getenv('CLAUDE_MODEL') ?: 'claude-haiku-5-5';
    }

    /** Pfad des Schlüssels auf dem Host (für Fehlermeldungen) */
    public function getApiKeyDatei(?string $anbieter = null): string
    {
        return match ($anbieter ?? $this->getAnbieter()) {
            'openai' => 'secrets/openai_api_key',
            'bedrock' => $this->getBedrockIam() !== null ? 'secrets/bedrock_iam.json' : 'secrets/bedrock_api_key',
            default => 'secrets/anthropic_api_key',
        };
    }

    /**
     * API-Schlüssel des Anbieters als Docker-Secret (secrets/openai_api_key, secrets/anthropic_api_key bzw. secrets/bedrock_api_key
     * auf dem Host). Bei Bedrock mit IAM-Zugangsschlüssel: die Access Key ID (nur als „eingerichtet“-Zeichen).
     */
    public function getApiKey(?string $anbieter = null): ?string
    {
        $anbieter ??= $this->getAnbieter();
        if ($anbieter === 'bedrock' && ($iam = $this->getBedrockIam()) !== null) {
            return $iam['access_key_id'];
        }
        $datei = match ($anbieter) {
            'openai' => 'openai_api_key',
            'bedrock' => 'bedrock_api_key',
            default => 'anthropic_api_key',
        };
        $key = @file_get_contents('/run/secrets/' . $datei);
        return $key !== false && trim($key) !== '' ? trim($key) : null;
    }

    /**
     * IAM-Zugangsschlüssel für Bedrock (AWS-Signatur SigV4) aus secrets/bedrock_iam.json:
     * {"access_key_id": "AKIA…", "secret_access_key": "…"}. Hat Vorrang vor dem Bedrock-API-Schlüssel, den
     * Organisationsrichtlinien (SCP auf bedrock:CallWithBearerToken) sperren können.
     * @return array{access_key_id: string, secret_access_key: string}|null
     */
    public function getBedrockIam(): ?array
    {
        $j = json_decode((string)@file_get_contents('/run/secrets/bedrock_iam'), true);
        $id = trim((string)($j['access_key_id'] ?? ''));
        $geheim = trim((string)($j['secret_access_key'] ?? ''));
        return $id !== '' && $geheim !== '' ? ['access_key_id' => $id, 'secret_access_key' => $geheim] : null;
    }

    /** Anmeldeart bei Bedrock: „iam“, „api“ oder null (nicht eingerichtet) */
    public function getBedrockAnmeldung(): ?string
    {
        if ($this->getBedrockIam() !== null) {
            return 'iam';
        }
        return $this->getApiKey('bedrock') !== null ? 'api' : null;
    }

    /**
     * KI-Client des eingestellten Anbieters.
     * @param int|null $timeout Sekunden für lange Antworten (Stadtrat)
     * @param string|null $anbieter abweichender Anbieter (Verbindungstest in der Administration)
     */
    public function ki(string $stufe = 'standard', ?string $claudeModell = null, ?int $timeout = null, ?string $anbieter = null): components\KiModell
    {
        $anbieter ??= $this->getAnbieter();
        $key = $this->getApiKey($anbieter) ?? throw new \RuntimeException('Kein KI-Schlüssel (' . $this->getApiKeyDatei($anbieter) . ')');
        if ($anbieter === 'openai') {
            return new components\OpenAi($key, $this->getModel($stufe, null, $anbieter), getenv('OPENAI_REASONING') === false ? 'low' : (getenv('OPENAI_REASONING') === 'aus' ? '' : getenv('OPENAI_REASONING')), $timeout ?? 300,
                getenv('OPENAI_BASE_URL') ?: 'https://api.openai.com/v1');
        }
        require_once __DIR__ . '/vendor/autoload.php';
        return new components\Claude($key, $this->getModel($stufe, $claudeModell, $anbieter), $timeout,
            $anbieter === 'bedrock' ? $this->getBedrockRegion() : null, $anbieter === 'bedrock' ? $this->getBedrockIam() : null);
    }

    /** Administration → KI-Anbieter (auch „Konfigurieren“ in der Modulliste) */
    public function getConfigUrl()
    {
        return \yii\helpers\Url::to(['/ki-assistent/admin/index']);
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
            return \Yii::t('KiAssistentModule.base', 'Uffi ist gerade sehr gefragt. Bitte versuch es gleich noch einmal.');
        }
        try {
            [$requests, $in, $out, $guests] = $this->getUsage();
            if ($requests >= $this->monthlyLimit || $in + 5 * $out >= $this->monthlyTokenLimit) {
                // einmal je Monat protokollieren, damit die Pause nicht unbemerkt bleibt
                if (\Yii::$app->cache->add('ki-pause-gemeldet-' . date('Y-m'), 1, 32 * 86400)) {
                    \Yii::warning("KI-Assistent: Monatsgrenze erreicht ($requests Anfragen, $in Eingabe- / $out Ausgabe-Tokens), Uffi pausiert bis zum Monatsende", 'ki-assistent');
                }
                return \Yii::t('KiAssistentModule.base', 'Uffi macht für diesen Monat Pause – das Kontingent ist aufgebraucht. Ab dem 1. geht es weiter.');
            }
            $gast = \Yii::$app->user->isGuest;
            if ($gast && $guests >= $this->guestMonthlyLimit) {
                return \Yii::t('KiAssistentModule.base', 'Ohne Konto ist Uffis Kontingent für diesen Monat aufgebraucht. Mit einem kostenlosen Konto geht es weiter.');
            }
            $cache = \Yii::$app->cache;
            $stunde = 'ki-stunde-' . $this->ident() . '-' . date('YmdH');
            $tag = 'ki-tag-' . $this->ident() . '-' . date('Ymd');
            if ((int)$cache->get($stunde) >= $this->hourlyLimit || (int)$cache->get($tag) >= $this->dailyLimit) {
                return \Yii::t('KiAssistentModule.base', 'Du hast schon viele Fragen gestellt. Bitte versuch es später noch einmal.');
            }
            $cache->set($stunde, (int)$cache->get($stunde) + 1, 3600);
            $cache->set($tag, (int)$cache->get($tag) + 1, 86400);
            $this->saveUsage([$requests + 1, $in, $out, $guests + ($gast ? 1 : 0)]);
            return null;
        } finally {
            $mutex->release('ki-assistent-limit');
        }
    }

    /** Prüft die Grenzen für eine neue Übersetzung und zählt sie; null oder Grund der Ablehnung (in der Sprache des Nutzers) */
    public function reserveUebersetzung(): ?string
    {
        $mutex = \Yii::$app->mutex;
        if (!$mutex->acquire('ki-uebersetzung-limit', 5)) {
            return \Yii::t('KiAssistentModule.base', 'Gerade sind viele Übersetzungen unterwegs. Bitte versuch es gleich noch einmal.');
        }
        try {
            $key = 'uebersetzung-' . date('Y-m');
            $usage = json_decode((string)$this->settings->get($key), true);
            [$anzahl, $in, $out] = array_values(array_map('intval', is_array($usage) ? $usage + [0, 0, 0] : [0, 0, 0]));
            if ($anzahl >= $this->uebersetzungMonat) {
                return \Yii::t('KiAssistentModule.base', 'Das Übersetzungs-Kontingent für diesen Monat ist aufgebraucht. Ab dem 1. geht es weiter.');
            }
            $cache = \Yii::$app->cache;
            $stunde = 'ki-ue-stunde-' . $this->ident() . '-' . date('YmdH');
            if ((int)$cache->get($stunde) >= $this->uebersetzungStunde) {
                return \Yii::t('KiAssistentModule.base', 'Du hast in dieser Stunde schon viel übersetzt. Bitte versuch es später noch einmal.');
            }
            $cache->set($stunde, (int)$cache->get($stunde) + 1, 3600);
            $this->settings->set($key, json_encode([$anzahl + 1, $in, $out]));
            return null;
        } finally {
            $mutex->release('ki-uebersetzung-limit');
        }
    }

    public function addUebersetzungUsage(int $in, int $out): void
    {
        $mutex = \Yii::$app->mutex;
        $mutex->acquire('ki-uebersetzung-limit', 5);
        try {
            $key = 'uebersetzung-' . date('Y-m');
            $usage = json_decode((string)$this->settings->get($key), true);
            [$anzahl, $i, $o] = array_values(array_map('intval', is_array($usage) ? $usage + [0, 0, 0] : [0, 0, 0]));
            $this->settings->set($key, json_encode([$anzahl, $i + $in, $o + $out]));
        } finally {
            $mutex->release('ki-uebersetzung-limit');
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

    /** Testläufe (setup/ki, ki-test, Modultests) zählen getrennt, damit sie Uffi nicht für alle abschalten: [Eingabe-, Ausgabe-Tokens] */
    public function getTestUsage(): array
    {
        $usage = json_decode((string)$this->settings->get('usage-test-' . date('Y-m')), true);
        return array_values(array_map('intval', is_array($usage) ? $usage + [0, 0] : [0, 0]));
    }

    public function addTestUsage(int $promptTokens, int $outputTokens): void
    {
        [$in, $out] = $this->getTestUsage();
        $this->settings->set('usage-test-' . date('Y-m'), json_encode([$in + $promptTokens, $out + $outputTokens]));
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
