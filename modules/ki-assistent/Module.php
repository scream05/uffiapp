<?php

namespace humhub\modules\kiAssistent;

class Module extends \humhub\components\Module
{
    /** Obergrenze an Anfragen pro Kalendermonat, damit das Claude-Guthaben nicht unbemerkt aufgebraucht wird */
    public int $monthlyLimit = 1000;

    /** Obergrenze je Konto bzw. (bei Gästen) je IP-Adresse und Stunde, gegen Missbrauch der offenen Nutzung */
    public int $hourlyLimit = 20;

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

    /** Zählt eine Anfrage je Konto bzw. IP-Adresse; false, wenn das Stundenlimit erreicht ist */
    public function takeHourly(): bool
    {
        $who = \Yii::$app->user->isGuest ? 'ip-' . \Yii::$app->request->userIP : 'u-' . \Yii::$app->user->id;
        $key = 'ki-stunde-' . $who . '-' . date('YmdH');
        $n = (int)\Yii::$app->cache->get($key) + 1;
        if ($n > $this->hourlyLimit) {
            return false;
        }
        \Yii::$app->cache->set($key, $n, 3600);
        return true;
    }

    /** Zähler für den laufenden Monat: [Anfragen, Eingabe-Tokens, Ausgabe-Tokens] */
    public function getUsage(): array
    {
        $usage = json_decode((string)$this->settings->get('usage-' . date('Y-m')), true);
        return is_array($usage) ? $usage + [0, 0, 0] : [0, 0, 0];
    }

    public function addUsage(int $promptTokens, int $outputTokens): void
    {
        [$requests, $in, $out] = $this->getUsage();
        $this->settings->set('usage-' . date('Y-m'), json_encode([$requests + 1, $in + $promptTokens, $out + $outputTokens]));
    }
}
