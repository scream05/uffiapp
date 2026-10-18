<?php

namespace humhub\modules\vereine\notifications;

use humhub\helpers\Html;
use humhub\modules\notification\components\BaseNotification;
use humhub\modules\vereine\models\VereinBeitrag;
use Yii;

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
        $space = Yii::$app->getModule('vereine')->getDirectorySpace();
        return $space ? $space->createUrl('/vereine/beitrag/pruefen', [], true) : parent::getUrl();
    }

    public function getMailSubject()
    {
        return Yii::t('VereineModule.base', '{name} hat einen Beitrag für {verein} eingereicht', ['name' => $this->originator->displayName, 'verein' => $this->source->verein->name]);
    }

    public function html()
    {
        return Yii::t('VereineModule.base', '{name} hat einen Beitrag für {verein} zur Prüfung eingereicht.', [
            'name' => Html::tag('strong', Html::encode($this->originator->displayName)),
            'verein' => Html::tag('strong', Html::encode($this->source->verein->name))]);
    }
}
