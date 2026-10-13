<?php

namespace humhub\modules\maengelmelder\jobs;

use humhub\modules\maengelmelder\components\Bauhof;
use humhub\modules\queue\ActiveJob;

/** Eingegangene WhatsApp-Nachricht eines Bauhof-Mitarbeiters auswerten (Foto laden, Knopf ausführen, zuordnen) */
class BauhofJob extends ActiveJob
{
    public int $id = 0;

    public function run()
    {
        Bauhof::verarbeiten($this->id);
    }
}
