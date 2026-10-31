<?php

namespace humhub\modules\stadtrat\jobs;

use humhub\modules\queue\LongRunningActiveJob;
use humhub\modules\stadtrat\components\Erklaert;

/** Vorlesefassung erzeugen und prüfen (Gemini + Claude, bis zu drei Versuche) */
class AudioJob extends LongRunningActiveJob
{
    public int $sitzungId;

    public function run()
    {
        Erklaert::audio($this->sitzungId);
    }
}
