<?php

namespace humhub\modules\stadtrat\jobs;

use humhub\modules\queue\LongRunningActiveJob;
use humhub\modules\stadtrat\components\Erklaert;

/** Entwurf der Kurzfassung aus der Niederschrift (Claude, dauert ein bis mehrere Minuten) */
class EntwurfJob extends LongRunningActiveJob
{
    public int $sitzungId;

    public function run()
    {
        Erklaert::entwurf($this->sitzungId);
    }
}
