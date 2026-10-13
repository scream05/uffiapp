<?php

namespace humhub\modules\designwahl\widgets;

use humhub\modules\space\widgets\InviteButton;

/**
 * Knopf „Einladen“ im Space-Kopf ausblenden (alle Spaces, auch für Admins).
 * Ersetzt InviteButton per DI-Container (Events::onBeforeRequest), der Kern bleibt unverändert.
 */
class OhneEinladen extends InviteButton
{
    public function run()
    {
        return '';
    }
}
