<?php

namespace humhub\modules\eilmeldung\notifications;

use humhub\modules\notification\components\NotificationCategory;
use humhub\modules\notification\targets\BaseTarget;
use humhub\modules\notification\targets\MailTarget;
use Yii;

class EilmeldungCategory extends NotificationCategory
{
    public $id = 'eilmeldung';
    public $sortOrder = 85;

    public function getTitle()
    {
        return Yii::t('EilmeldungModule.base', 'Eilmeldungen');
    }

    public function getDescription()
    {
        return Yii::t('EilmeldungModule.base', 'Wichtiges für ganz Uffenheim: Straßensperrungen, Wasser, Strom, Unwetter und amtliche Warnungen.');
    }

    /** Glocke und Push ja, E-Mail nur, wenn man es selbst einschaltet */
    public function getDefaultSetting(BaseTarget $target)
    {
        return $target->id === MailTarget::getId() ? false : parent::getDefaultSetting($target);
    }
}
