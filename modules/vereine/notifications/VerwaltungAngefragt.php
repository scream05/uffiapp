<?php

namespace humhub\modules\vereine\notifications;

use humhub\helpers\Html;
use humhub\modules\notification\components\BaseNotification;
use humhub\modules\vereine\models\Verein;

/**
 * An Admins und bisherige Verwalter: jemand möchte einen Verein verwalten (oder hat einen neuen Verein vorgeschlagen).
 *
 * @property Verein $source
 */
class VerwaltungAngefragt extends BaseNotification
{
    public $moduleId = 'vereine';
    public $requireOriginator = true;

    public function getUrl()
    {
        $space = \Yii::$app->getModule('vereine')->getDirectorySpace();
        return $space ? $space->createUrl('/vereine/verein/verwalter', ['id' => $this->source->id], true) : parent::getUrl();
    }

    public function getMailSubject()
    {
        return $this->infoText($this->originator->displayName, $this->source->name);
    }

    public function html()
    {
        return $this->infoText(Html::tag('strong', Html::encode($this->originator->displayName)),
            Html::tag('strong', Html::encode($this->source->name)));
    }

    private function infoText($user, $verein)
    {
        return $this->source->status === Verein::STATUS_VORGESCHLAGEN
            ? "$user hat den Verein $verein für das Verzeichnis vorgeschlagen und möchte ihn verwalten."
            : "$user möchte den Verein $verein im Vereinsverzeichnis verwalten.";
    }
}
