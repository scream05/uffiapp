<?php

namespace humhub\modules\tanken;

class Module extends \humhub\components\Module
{
    /** Space, in dem die Seite „Spritpreise“ läuft (URL bleibt beim Umbenennen gleich) */
    public string $spaceUrl = 'gewerbe';

    /** Mittelpunkt der Suche: Uffenheim, Marktplatz */
    public float $lat = 49.5448;
    public float $lng = 10.2305;

    /** Umkreis der Abfrage in km (Tankerkönig: höchstens 25); die Seite bietet kleinere Umkreise zur Auswahl */
    public int $radius = 15;
    public array $umkreise = [5, 10, 15];

    /** Kasten auf der Startseite: Tankstellen in diesem Umkreis (alle, sortiert nach Entfernung) */
    public int $kastenRadius = 5;

    /** Preise höchstens so oft neu abrufen (Tankerkönig: nicht öfter als alle 5 Minuten, nur bei Bedarf) */
    public int $maxAlterMinuten = 10;

    /** Ältere Daten gelten als veraltet und werden nicht mehr angezeigt */
    public int $veraltetMinuten = 90;

    /** Beobachtete Tiefstpreise für „Wann ist Tanken günstig?“ so viele Tage behalten */
    public int $verlaufTage = 90;

    /** Persönlicher API-Schlüssel von Tankerkönig (Docker-Secret, nie veröffentlichen) */
    public string $apiKeyDatei = '/run/secrets/tankerkoenig_api_key';

    public function getApiKey(): ?string
    {
        $key = @file_get_contents($this->apiKeyDatei);
        return $key !== false && preg_match('/^[0-9a-f-]{36}$/i', trim($key)) ? trim($key) : null;
    }

    /** Ohne Schlüssel bleibt alles ausgeblendet */
    public function aktiv(): bool
    {
        return $this->getApiKey() !== null;
    }
}
