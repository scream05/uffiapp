<?php

namespace humhub\modules\designwahl\widgets;

use humhub\modules\space\widgets\HeaderCounterSet;
use Yii;

/**
 * Zähler im Space-Kopf ohne „Beiträge“ (in allen Spaces; Mitglieder/Follower steuern die Space-Einstellungen).
 * Ersetzt HeaderCounterSet per DI-Container (Events::onBeforeRequest), der Kern bleibt unverändert.
 */
class SpaceZaehler extends HeaderCounterSet
{
    public function init()
    {
        parent::init();
        $beitraege = Yii::t('SpaceModule.base', 'Posts');
        $this->counters = array_values(array_filter($this->counters, fn($c) => $c->label !== $beitraege));
    }

    public function run()
    {
        // Ohne Zähler kein leerer Block
        return $this->counters ? parent::run() : '';
    }
}
