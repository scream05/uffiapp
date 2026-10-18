<?php

namespace humhub\modules\vereine\notifications;

use humhub\helpers\Html;
use humhub\modules\notification\components\BaseNotification;
use humhub\modules\vereine\models\Verein;
use Yii;

/**
 * An den Nutzer: Verwaltung eines Vereins bestätigt.
 *
 * @property Verein $source
 */
class VerwaltungBestaetigt extends BaseNotification
{
    public $moduleId = 'vereine';
    public $requireOriginator = true;

    public function getUrl()
    {
        return $this->source->getUrl(true);
    }

    public function getMailSubject()
    {
        return Yii::t('VereineModule.base', 'Du kannst jetzt den Verein {verein} verwalten', ['verein' => $this->source->name]);
    }

    public function html()
    {
        return Yii::t('VereineModule.base', '{name} hat bestätigt: Du kannst jetzt den Verein {verein} verwalten (Vorstellungsseite und Termine).', [
            'name' => Html::tag('strong', Html::encode($this->originator->displayName)),
            'verein' => Html::tag('strong', Html::encode($this->source->name))]);
    }
}
