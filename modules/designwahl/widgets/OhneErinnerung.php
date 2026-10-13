<?php

namespace humhub\modules\designwahl\widgets;

use humhub\modules\calendar\widgets\ReminderLink;

/**
 * Kalender: Link „Erinnerung einstellen“ an Terminen ausblenden (Kalender-Erinnerungen sind systemweit aus,
 * die Abfuhr-Erinnerung ist ein eigenes Modul). Ersetzt ReminderLink per DI-Container (Events::onBeforeRequest).
 */
class OhneErinnerung extends ReminderLink
{
    public function run()
    {
        return '';
    }
}
