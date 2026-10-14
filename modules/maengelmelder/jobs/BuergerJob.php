<?php

namespace humhub\modules\maengelmelder\jobs;

use humhub\modules\maengelmelder\components\Buergerinfo;
use humhub\modules\queue\ActiveJob;

/** Eingegangene WhatsApp-Nachricht einer meldenden Person auswerten (Foto, STOPP, Glocke, Weiterleitung) */
class BuergerJob extends ActiveJob
{
    public int $id = 0;

    public function run()
    {
        Buergerinfo::verarbeiten($this->id);
    }
}
