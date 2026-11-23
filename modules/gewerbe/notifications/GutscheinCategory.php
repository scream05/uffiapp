<?php

namespace humhub\modules\gewerbe\notifications;

use humhub\modules\notification\components\NotificationCategory;
use humhub\modules\notification\targets\BaseTarget;
use humhub\modules\notification\targets\MailTarget;
use humhub\modules\notification\targets\MobileTarget;
use Yii;

class GutscheinCategory extends NotificationCategory
{
    public $id = 'gutscheine';
    public $sortOrder = 95;

    public function getTitle()
    {
        return Yii::t('GewerbeModule.base', 'Neue Gutscheine');
    }

    public function getDescription()
    {
        return Yii::t('GewerbeModule.base', 'Ein Betrieb aus Uffenheim hat einen neuen Rabatt-Gutschein veröffentlicht.');
    }

    /**
     * Nur die Glocke ist voreingestellt. Push (Handy/Browser) und E-Mail nur, wenn man es selbst einschaltet
     * (Schalter auf der Gutscheinseite oder Benachrichtigungs-Einstellungen): Gutscheine sind Werbung, dafür verlangt
     * Apple (Richtlinie 4.5.4) ein ausdrückliches Opt-in. Seit 23.11.2026, vorher war Push voreingestellt.
     */
    public function getDefaultSetting(BaseTarget $target)
    {
        return in_array($target->id, [MailTarget::getId(), MobileTarget::getId()], true) ? false : parent::getDefaultSetting($target);
    }

    /** Ist Push für neue Gutscheine bei diesem Konto eingeschaltet? */
    public static function pushAn(\humhub\modules\user\models\User $user): bool
    {
        $target = self::pushZiel();
        return $target !== null && $target->isCategoryEnabled(new self(), $user);
    }

    public static function pushSetzen(\humhub\modules\user\models\User $user, bool $an): void
    {
        $target = self::pushZiel();
        if ($target !== null) {
            Yii::$app->getModule('notification')->settings->user($user)->set($target->getSettingKey(new self()), $an ? 1 : 0);
        }
    }

    /** Push-Ziel (id „mobile“, Handy-App und Browser über fcm-push) */
    private static function pushZiel(): ?BaseTarget
    {
        foreach (Yii::$app->notification->getTargets() as $target) {
            if ($target->id === MobileTarget::getId()) {
                return $target;
            }
        }
        return null;
    }
}
