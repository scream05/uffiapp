<?php

namespace humhub\modules\wochenblick\notifications;

use humhub\modules\notification\components\NotificationCategory;
use humhub\modules\notification\targets\BaseTarget;
use humhub\modules\notification\targets\MailTarget;
use Yii;

class WochenblickCategory extends NotificationCategory
{
    public $id = 'wochenblick';
    public $sortOrder = 95;

    public function getTitle()
    {
        return Yii::t('WochenblickModule.base', 'Wochenüberblick');
    }

    public function getDescription()
    {
        return Yii::t('WochenblickModule.base', 'Freitags der Überblick über Wochenende und kommende Woche (einschalten unter Konto → Wochenüberblick).');
    }

    /** Glocke und Push; die E-Mail mit dem ganzen Überblick schickt das Modul selbst (Einstellung im Konto) */
    public function getDefaultSetting(BaseTarget $target)
    {
        return $target->id === MailTarget::getId() ? false : parent::getDefaultSetting($target);
    }
}
