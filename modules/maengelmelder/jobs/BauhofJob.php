<?php

namespace humhub\modules\maengelmelder\jobs;

use humhub\modules\maengelmelder\components\Bauhof;
use humhub\modules\queue\ActiveJob;

/** Eingegangene WhatsApp-Nachricht eines Bauhof-Mitarbeiters auswerten (Foto laden, Knopf ausführen, zuordnen) */
class BauhofJob extends ActiveJob
{
    public int $id = 0;
    /** true: Frist der Rückfrage „Wohin gehört das Foto?“ abgelaufen */
    public bool $frist = false;

    public function run()
    {
        $this->frist ? Bauhof::frageFrist($this->id) : Bauhof::verarbeiten($this->id);
    }
}
