<?php

namespace humhub\modules\gewerbe\notifications;

use humhub\modules\notification\components\NotificationCategory;
use humhub\modules\notification\targets\BaseTarget;
use humhub\modules\notification\targets\MailTarget;

class GutscheinCategory extends NotificationCategory
{
    public $id = 'gutscheine';
    public $sortOrder = 95;

    public function getTitle()
    {
        return 'Neue Gutscheine';
    }

    public function getDescription()
    {
        return 'Ein Betrieb aus Uffenheim hat einen neuen Rabatt-Gutschein veröffentlicht.';
    }

    /** Glocke und Push ja, E-Mail nur, wenn man es selbst einschaltet */
    public function getDefaultSetting(BaseTarget $target)
    {
        return $target->id === MailTarget::getId() ? false : parent::getDefaultSetting($target);
    }
}
