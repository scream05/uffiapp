<?php

namespace humhub\modules\maengelmelder\jobs;

use humhub\modules\maengelmelder\components\Buergerinfo;
use humhub\modules\queue\ActiveJob;

/** Eingegangene WhatsApp-Nachricht einer meldenden Person auswerten (Foto, STOPP, Glocke, Weiterleitung) */
class BuergerJob extends ActiveJob
{
    public int $id = 0;
    /** true: Frist der Rückfrage „Wohin gehört das Foto?“ abgelaufen */
    public bool $frist = false;
    /** Antwort auf die Rückfrage („bm:<Nr>“ oder „bt“) von Nummer $von */
    public string $knopf = '';
    public string $von = '';

    public function run()
    {
        if ($this->knopf !== '') {
            Buergerinfo::knopf($this->von, $this->knopf);
        } elseif ($this->frist) {
            Buergerinfo::frageFrist($this->id);
        } else {
            Buergerinfo::verarbeiten($this->id);
        }
    }
}
