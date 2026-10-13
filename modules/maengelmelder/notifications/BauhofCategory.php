<?php

namespace humhub\modules\maengelmelder\notifications;

use humhub\modules\notification\components\NotificationCategory;

class BauhofCategory extends NotificationCategory
{
    public $id = 'bauhof';
    public $sortOrder = 95;

    public function getTitle()
    {
        return 'Bauhof (WhatsApp)';
    }

    public function getDescription()
    {
        return 'Nachrichten, Fotos und Statusänderungen der Bauhof-Mitarbeiter per WhatsApp.';
    }
}
