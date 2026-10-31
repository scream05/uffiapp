<?php

namespace humhub\modules\stadtrat\notifications;

use humhub\modules\notification\components\NotificationCategory;
use humhub\modules\notification\targets\BaseTarget;
use humhub\modules\notification\targets\MailTarget;
use Yii;

class StadtratCategory extends NotificationCategory
{
    public $id = 'stadtrat';
    public $sortOrder = 88;

    public function getTitle()
    {
        return Yii::t('StadtratModule.base', 'Stadtrat');
    }

    public function getDescription()
    {
        return Yii::t('StadtratModule.base', 'Neue Tagesordnungen und Kurzfassungen von Stadtrat und Ausschüssen (nur wenn du sie auf der Seite „Stadtrat“ abonniert hast).');
    }

    /** Glocke und Push ja, E-Mail nur, wenn man es selbst einschaltet */
    public function getDefaultSetting(BaseTarget $target)
    {
        return $target->id === MailTarget::getId() ? false : parent::getDefaultSetting($target);
    }
}
