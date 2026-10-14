<?php

namespace humhub\modules\maengelmelder\notifications;

use humhub\modules\notification\components\NotificationCategory;
use humhub\modules\notification\targets\BaseTarget;
use humhub\modules\user\models\User;

class GastmeldungCategory extends NotificationCategory
{
    public $id = 'maengel_gastmeldung';
    public $sortOrder = 96;

    public function getTitle()
    {
        return 'Gastmeldungen (Bauhof)';
    }

    public function getDescription()
    {
        return 'Neue Mängelmeldungen ohne Konto, die auf Freigabe warten.';
    }

    /** Standard: nur Glocke (keine E-Mail/Push), jeder kann es in den Einstellungen ändern */
    public function getDefaultSetting(BaseTarget $target)
    {
        return $target->id === 'web';
    }

    /** Nur für die Bauhofleitung und Admins in den Einstellungen sichtbar */
    public function isVisible(?User $user = null)
    {
        return $user === null || $user->isSystemAdmin() || in_array((int)$user->id,
            array_map(fn($u) => (int)$u->id, \humhub\modules\maengelmelder\components\Bauhof::empfaenger()), true);
    }
}
