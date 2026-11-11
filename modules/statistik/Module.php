<?php

namespace humhub\modules\statistik;

class Module extends \humhub\components\Module
{
    /** Rohdaten (ein Eintrag je Seitenaufruf) so viele Monate behalten, danach löschen (Vorjahresvergleich möglich) */
    public int $aufbewahrungMonate = 14;

    /** Pause, ab der ein neuer Besuch beginnt (wie Matomo/Plausible) */
    public int $besuchPauseMinuten = 30;

    /** Aufrufe von Admins mitzählen? Standard nein: Pflege und Tests verfälschen sonst die Zahlen */
    public bool $adminsZaehlen = false;

    /**
     * Massenabrufe: Ein Gast mit mehr als so vielen Aufrufen in {@see $massenMinuten} Minuten ist ein Programm
     * (z. B. Scraper mit Handy-Browserkennung) – er wird für den ganzen Tag aus der Statistik genommen
     */
    public int $massenAufrufe = 30;
    public int $massenMinuten = 3;

    /** Browser mit „Do Not Track“ oder „Global Privacy Control“ werden nicht gezählt */
    public bool $dntBeachten = true;

    /** Wiederkehrende Abrufe von Hilfsseiten, die keine eigene Seite sind */
    public array $ausgeschlosseneRouten = [
        'file/file/download', 'file/file/upload', 'live/poll/index', 'notification/list/index',
        'user/auth/external', 'web/pwa-manifest/index', 'web/pwa-service-worker/index',
        'web/pwa-offline/index', 'statistik/admin/index',
    ];
}
