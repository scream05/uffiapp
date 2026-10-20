<?php

namespace humhub\modules\tanken\jobs;

use humhub\modules\queue\ActiveJob;
use humhub\modules\tanken\components\Spritpreise;

/** Stündlicher Abruf über die Warteschlange, mit zufälliger Verzögerung (siehe Events::onCronHourly) */
class Abruf extends ActiveJob
{
    public function run()
    {
        Spritpreise::daten();
    }
}
