<?php

namespace humhub\modules\gewerbe\notifications;

use humhub\helpers\Html;
use humhub\modules\gewerbe\models\Betrieb;
use humhub\modules\notification\components\BaseNotification;
use Yii;

/**
 * An Admins und bisherige Verwalter: jemand möchte einen Betrieb verwalten (oder hat einen neuen eingetragen).
 *
 * @property Betrieb $source
 */
class VerwaltungAngefragt extends BaseNotification
{
    public $moduleId = 'gewerbe';
    public $requireOriginator = true;

    public function getUrl()
    {
        $space = \Yii::$app->getModule('gewerbe')->getDirectorySpace();
        return $space ? $space->createUrl('/gewerbe/betrieb/verwalter', ['id' => $this->source->id], true) : parent::getUrl();
    }

    public function getMailSubject()
    {
        return $this->infoText($this->originator->displayName, $this->source->name);
    }

    public function html()
    {
        $params = ['user' => Html::tag('strong', Html::encode($this->originator->displayName)), 'betrieb' => Html::tag('strong', Html::encode($this->source->name))];
        return $this->source->status === Betrieb::STATUS_VORGESCHLAGEN
            ? Yii::t('GewerbeModule.base', '{user} hat den Betrieb {betrieb} im Gewerbeverzeichnis eingetragen und möchte ihn verwalten.', $params)
            : Yii::t('GewerbeModule.base', '{user} möchte den Betrieb {betrieb} im Gewerbeverzeichnis verwalten.', $params);
    }

    private function infoText($user, $betrieb)
    {
        return $this->source->status === Betrieb::STATUS_VORGESCHLAGEN
            ? Yii::t('GewerbeModule.base', '{user} hat den Betrieb {betrieb} im Gewerbeverzeichnis eingetragen und möchte ihn verwalten.', ['user' => $user, 'betrieb' => $betrieb])
            : Yii::t('GewerbeModule.base', '{user} möchte den Betrieb {betrieb} im Gewerbeverzeichnis verwalten.', ['user' => $user, 'betrieb' => $betrieb]);
    }
}
