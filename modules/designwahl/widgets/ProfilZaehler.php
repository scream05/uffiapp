<?php

namespace humhub\modules\designwahl\widgets;

use humhub\modules\user\widgets\ProfileHeaderCounterSet;

/**
 * Profilkopf ohne Zähler (Follower, Folgt, Spaces, ggf. Freunde).
 * Ersetzt ProfileHeaderCounterSet per DI-Container (Events::onBeforeRequest), der Kern bleibt unverändert.
 */
class ProfilZaehler extends ProfileHeaderCounterSet
{
    public function init()
    {
        parent::init();
        $this->counters = [];
    }

    public function run()
    {
        return '';
    }
}
