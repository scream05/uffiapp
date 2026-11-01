<?php

namespace humhub\modules\stadtrat;

use humhub\modules\user\models\User;

class Module extends \humhub\components\Module
{
    /** /stadtrat leitet in den Space weiter */
    public $defaultRoute = 'index';

    /** Space, in dem die Seite „Stadtrat“ läuft (URL bleibt beim Umbenennen gleich) */
    public string $spaceUrl = 'rathaus-uffenheim';

    /**
     * Wer Seite und Kasten sieht (Benutzernamen); null = alle, auch Gäste.
     * 31.10.2026 nur ['admin'] zum Ausprobieren, seit 01.11.2026 für alle freigegeben.
     */
    public ?array $freigabe = null;

    /** Ratsinformationssystem der VG Uffenheim (komuna/KIC): öffentliche Gast-Schnittstelle */
    public string $api = 'https://risapi3.komuna.net/';
    public string $orgKey = 'RIS52';
    public string $risUrl = 'https://ris.komuna.net/vguffenheim';

    /** Mandant „Stadt Uffenheim“ im RIS (die VG führt dort auch ihre Mitgliedsgemeinden) */
    public int $mandant = 757;

    /** Abgerufener Zeitraum: so viele Tage zurück und voraus */
    public int $tageZurueck = 400;
    public int $tageVoraus = 180;

    /** „Stadtrat kurz erklärt“: Niederschriften (PDF) und Aufnahmen, nicht öffentlich erreichbar (humhub-data/stadtrat) */
    public string $verzeichnis = '/data/stadtrat';

    /** Claude-Modell für den Entwurf (Genauigkeit vor Preis) und für den Abgleich der Aufnahme */
    public string $modellEntwurf = 'claude-opus-5-5';
    public string $modellPruefung = 'claude-sonnet-5-5';

    /** Gemini: Sprachmodell und Stimme für die Vorlesefassung, Modell für die Abschrift zur Prüfung */
    public string $ttsModell = 'gemini-3.8-flash-tts';
    public string $stimme = 'Kore';
    public string $abschriftModell = 'gemini-3.8-flash';

    /**
     * Regieanweisung vor dem Vorlesetext – leer lassen: Das Sprachmodell liest jede Anweisung mit vor, auf Deutsch
     * wie auf Englisch (vom Nutzer am 01.11.2026 gehört; die Abschrift hatte den englischen Satz verschluckt).
     * Eine getrennte Systemanweisung lehnt das Sprachmodell ab. Der Ton kommt allein von der Stimme.
     */
    public string $regie = '';

    /** Kurzfassungen schreiben und freigeben: System-Admins (und nur, solange das Modul für sie freigegeben ist) */
    public function darfBearbeiten(?User $user): bool
    {
        return $user !== null && $user->isSystemAdmin() && $this->istFreigegeben($user);
    }

    public function istFreigegeben(?User $user): bool
    {
        if ($this->freigabe === null) {
            return true;
        }
        return $user !== null && in_array($user->username, $this->freigabe, true);
    }

    public function sitzungUrl(int $risId): string
    {
        return $this->risUrl . '/app/sitzungen/' . $risId;
    }
}
