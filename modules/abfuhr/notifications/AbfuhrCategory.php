<?php

namespace humhub\modules\abfuhr\notifications;

use humhub\modules\notification\components\NotificationCategory;
use humhub\modules\notification\targets\BaseTarget;
use humhub\modules\notification\targets\MailTarget;

class AbfuhrCategory extends NotificationCategory
{
    public $id = 'abfuhr';
    public $sortOrder = 90;

    public function getTitle()
    {
        return 'Abfuhr-Erinnerung';
    }

    public function getDescription()
    {
        return 'Erinnerung an die Müllabfuhr (einrichten unter Konto → Abfuhr-Erinnerung).';
    }

    /** Glocke und Push ja, E-Mail nur, wenn man es selbst einschaltet */
    public function getDefaultSetting(BaseTarget $target)
    {
        return $target->id === MailTarget::getId() ? false : parent::getDefaultSetting($target);
    }
}
