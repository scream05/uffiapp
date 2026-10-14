<?php

namespace humhub\modules\vereine\notifications;

use humhub\helpers\Html;
use humhub\modules\notification\components\BaseNotification;
use humhub\modules\vereine\models\VereinBeitrag;

/**
 * An Admins / „Vereine verwalten“: ein Vereinsverwalter hat einen Beitrag zur Prüfung eingereicht.
 *
 * @property VereinBeitrag $source
 */
class BeitragEingereicht extends BaseNotification
{
    public $moduleId = 'vereine';
    public $requireOriginator = true;

    public function getUrl()
    {
        $space = \Yii::$app->getModule('vereine')->getDirectorySpace();
        return $space ? $space->createUrl('/vereine/beitrag/pruefen', [], true) : parent::getUrl();
    }

    public function getMailSubject()
    {
        return "{$this->originator->displayName} hat einen Beitrag für {$this->source->verein->name} eingereicht";
    }

    public function html()
    {
        return Html::tag('strong', Html::encode($this->originator->displayName)) . ' hat einen Beitrag für '
            . Html::tag('strong', Html::encode($this->source->verein->name)) . ' zur Prüfung eingereicht.';
    }
}
