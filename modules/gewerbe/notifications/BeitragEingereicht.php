<?php

namespace humhub\modules\gewerbe\notifications;

use humhub\helpers\Html;
use humhub\modules\notification\components\BaseNotification;
use Yii;
use humhub\modules\gewerbe\models\BetriebBeitrag;

/**
 * An Admins / „Gewerbe verwalten“: ein Betriebsverwalter hat einen Beitrag zur Prüfung eingereicht.
 *
 * @property BetriebBeitrag $source
 */
class BeitragEingereicht extends BaseNotification
{
    public $moduleId = 'gewerbe';
    public $requireOriginator = true;

    public function getUrl()
    {
        $space = \Yii::$app->getModule('gewerbe')->getDirectorySpace();
        return $space ? $space->createUrl('/gewerbe/beitrag/pruefen', [], true) : parent::getUrl();
    }

    public function getMailSubject()
    {
        return "{$this->originator->displayName} hat einen Beitrag für {$this->source->betrieb->name} eingereicht";
    }

    public function html()
    {
        return Yii::t('GewerbeModule.base', '{name} hat einen Beitrag für {betrieb} zur Prüfung eingereicht.', [
            'name' => Html::tag('strong', Html::encode($this->originator->displayName)),
            'betrieb' => Html::tag('strong', Html::encode($this->source->betrieb->name))]);
    }
}
