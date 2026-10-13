<?php

namespace humhub\modules\designwahl\widgets;

use humhub\modules\space\widgets\HeaderCounterSet;

/**
 * Space-Kopf ohne Zähler (Beiträge, Mitglieder, Follower) in allen Spaces.
 * Ersetzt HeaderCounterSet per DI-Container (Events::onBeforeRequest), der Kern bleibt unverändert.
 */
class SpaceZaehler extends HeaderCounterSet
{
    public function init()
    {
        parent::init();
        $this->counters = [];
    }

    public function run()
    {
        // Ohne Zähler kein leerer Block
        return $this->counters ? parent::run() : '';
    }
}
