<?php

namespace humhub\modules\designwahl\widgets;

use humhub\modules\user\widgets\ProfileHeaderCounterSet;
use Yii;

/**
 * Profilkopf ohne „Follower“ und „Folgt“; übrige Zähler (z. B. Spaces) bleiben.
 * Ersetzt ProfileHeaderCounterSet per DI-Container (Events::onBeforeRequest).
 */
class ProfilZaehler extends ProfileHeaderCounterSet
{
    public function init()
    {
        parent::init();
        $weg = [Yii::t('UserModule.profile', 'Followers'), Yii::t('UserModule.profile', 'Following')];
        $this->counters = array_values(array_filter($this->counters, fn($c) => !in_array($c->label, $weg, true)));
    }

    public function run()
    {
        return $this->counters ? parent::run() : '';
    }
}
