<?php

namespace humhub\modules\whatsapp\events;

use yii\base\Event;

/**
 * Eingehende Nachricht bzw. Zustellstatus aus dem Webhook, bevor der Termin-Eingang sie übernimmt.
 * Andere Module (Mängelmelder/Bauhof) können sie für sich beanspruchen: $handled = true.
 */
class EmpfangEvent extends Event
{
    /** Nachrichten- bzw. Status-Objekt aus dem Webhook (messages[] bzw. statuses[]) */
    public array $daten = [];

    /** Profilname des Absenders laut WhatsApp */
    public ?string $profilname = null;
}
