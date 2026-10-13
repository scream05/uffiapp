<?php

namespace humhub\modules\whatsapp\jobs;

use humhub\modules\queue\ActiveJob;
use humhub\modules\whatsapp\components\Verarbeitung;

/** Wird nach jedem Webhook mit Verzögerung eingereiht (Sammelzeit für Bild + Text) */
class VerarbeitenJob extends ActiveJob
{
    public function run()
    {
        Verarbeitung::lauf();
    }
}
