<?php

namespace humhub\modules\notdienst;

class Module extends \humhub\components\Module
{
    /** Space, in dessen Menü die Notdienst-Seite verlinkt wird */
    public string $spaceUrl = 'rathaus-uffenheim'; // URL des Space, bleibt beim Umbenennen gleich

    /** Notrufnummern oben auf der Seite und für den KI-Assistenten: [Bezeichnung, Nummer, Hinweis] */
    public array $notrufe = [
        ['Notruf (Rettungsdienst, Feuerwehr)', '112', 'Lebensgefahr, Unfall, Brand – auch ohne Guthaben/SIM'],
        ['Polizei', '110', ''],
        ['Ärztlicher Bereitschaftsdienst', '116 117', 'außerhalb der Sprechzeiten, wenn es nicht lebensbedrohlich ist'],
        ['Giftnotruf München', '089 19240', 'bei Vergiftungen, rund um die Uhr'],
    ];

    /** Zahnärztlicher Notdienst (Link von der Seite „Im Notfall“ der Stadt) */
    public string $zahnarztUrl = 'https://www.zahnarzt-notdienst.de';

    /** Öffentliche Notdienstsuche der Kammer, falls unsere Daten fehlen */
    public string $suchUrl = 'https://www.blak.de/notdienst/oeffentliche-notdienstsuche/';

    /** Schnittstellen-Link der BLAK (Docker-Secret, an unsere Registrierung gebunden, nicht veröffentlichen) */
    public function getFeedUrl(): ?string
    {
        $url = @file_get_contents('/run/secrets/blak_notdienst_url');
        return $url !== false && trim($url) !== '' ? trim($url) : null;
    }
}
