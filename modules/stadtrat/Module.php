<?php

namespace humhub\modules\stadtrat;

use humhub\modules\stadtrat\notifications\TagesordnungNeu;
use humhub\modules\user\models\User;

class Module extends \humhub\components\Module
{
    /** /stadtrat leitet in den Space weiter */
    public $defaultRoute = 'index';

    /** Space, in dem die Seite „Stadtrat“ läuft (URL bleibt beim Umbenennen gleich) */
    public string $spaceUrl = 'rathaus-uffenheim';

    /**
     * Wer Seite, Kasten und Abo sieht (Benutzernamen); null = alle, auch Gäste.
     * Erst nur zum Ausprobieren (31.10.2026).
     */
    public ?array $freigabe = ['admin'];

    /** Ratsinformationssystem der VG Uffenheim (komuna/KIC): öffentliche Gast-Schnittstelle */
    public string $api = 'https://risapi3.komuna.net/';
    public string $orgKey = 'RIS52';
    public string $risUrl = 'https://ris.komuna.net/vguffenheim';

    /** Mandant „Stadt Uffenheim“ im RIS (die VG führt dort auch ihre Mitgliedsgemeinden) */
    public int $mandant = 757;

    /** Abgerufener Zeitraum: so viele Tage zurück und voraus */
    public int $tageZurueck = 400;
    public int $tageVoraus = 180;

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

    public function getNotifications()
    {
        return [TagesordnungNeu::class];
    }
}
