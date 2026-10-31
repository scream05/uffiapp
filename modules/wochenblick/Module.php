<?php

namespace humhub\modules\wochenblick;

use humhub\modules\user\models\User;
use humhub\modules\wochenblick\notifications\Wochenueberblick;

class Module extends \humhub\components\Module
{
    /**
     * Wer die Funktion sehen und einschalten darf (Benutzernamen); null = alle Konten.
     * Erst nur zum Ausprobieren (Wunsch Nutzer 31.10.2026), eingeschaltet wird immer selbst im Konto.
     */
    public ?array $freigabe = ['admin'];

    /** Versand: Wochentag (5 = Freitag) ab dieser Stunde, bis 3 Stunden danach (falls ein Lauf ausfällt) */
    public int $wochentag = 5;
    public int $stunde = 17;

    /** Höchstens so viele Veranstaltungen im Überblick (der Rest über den Link „alle Termine“) */
    public int $maxTermine = 12;

    public function istFreigegeben(?User $user): bool
    {
        return $user !== null && ($this->freigabe === null || in_array($user->username, $this->freigabe, true));
    }

    public function getNotifications()
    {
        return [Wochenueberblick::class];
    }
}
