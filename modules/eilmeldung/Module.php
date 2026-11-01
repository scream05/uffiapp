<?php

namespace humhub\modules\eilmeldung;

use humhub\modules\eilmeldung\notifications\EilmeldungNeu;
use humhub\modules\user\models\Group;
use humhub\modules\user\models\User;

class Module extends \humhub\components\Module
{
    /** /eilmeldung zeigt die Liste */
    public $defaultRoute = 'index';

    /**
     * Wer die Push-Nachricht bekommt (Benutzernamen); null = alle aktiven Konten (abbestellbar unter
     * Benachrichtigungen → „Eilmeldungen“). Bis 01.11.2026 nur ['admin'], seitdem alle.
     * Der Hinweis auf der Startseite gilt immer für alle, auch Gäste.
     */
    public ?array $empfaenger = null;

    /** Wer Eilmeldungen von Hand schreiben darf: System-Admins und diese Gruppe (wie im Mängelmelder) */
    public string $gruppe = 'Bauhof';

    /** Amtlicher Regionalschlüssel des Landkreises Neustadt a.d.Aisch-Bad Windsheim (NINA, Kreisebene) */
    public string $ars = '095750000000';

    /**
     * Ab welcher Schwere eine NINA-Warnung als Eilmeldung kommt: Wetterwarnungen des DWD erst ab „Severe“
     * (Unwetter), alle anderen (Bevölkerungsschutz, Polizei, Hochwasser) ab „Moderate“.
     */
    public array $ninaSchwere = ['DWD' => ['Severe', 'Extreme'], '*' => ['Moderate', 'Severe', 'Extreme']];

    /** Arten: Schlüssel => [Bezeichnung, Symbol (Font Awesome)] */
    public const ARTEN = [
        'sperrung' => ['Straßensperrung', 'road'],
        'wasser' => ['Wasser', 'tint'],
        'strom' => ['Strom', 'bolt'],
        'unwetter' => ['Unwetter', 'cloud'],
        'warnung' => ['Amtliche Warnung', 'exclamation-triangle'],
        'sonstiges' => ['Hinweis', 'info-circle'],
    ];

    /** Arten, die man von Hand wählen kann (ohne „Amtliche Warnung“, die kommt nur aus NINA) */
    public static function artenVonHand(): array
    {
        return array_values(array_diff(array_keys(self::ARTEN), ['warnung']));
    }

    public function artName(string $art): string
    {
        return \Yii::t('EilmeldungModule.base', self::ARTEN[$art][0] ?? 'Hinweis');
    }

    public function darfSchreiben(?User $user): bool
    {
        if ($user === null) {
            return false;
        }
        if ($user->isSystemAdmin()) {
            return true;
        }
        $g = Group::findOne(['name' => $this->gruppe]);
        return $g !== null && $g->isMember($user);
    }

    public function getNotifications()
    {
        return [EilmeldungNeu::class];
    }
}
