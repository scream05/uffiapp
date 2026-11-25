<?php

namespace humhub\modules\vereine\notifications;

use humhub\helpers\Html;
use humhub\modules\vereine\models\Verein;
use humhub\modules\notification\components\BaseNotification;
use Yii;

/**
 * An den Nutzer: ein anderer Verwalter hat ihn als Verwalter entfernt (damit niemand still verdrängt wird).
 *
 * @property Verein $source
 */
class VerwaltungEntfernt extends BaseNotification
{
    public $moduleId = 'vereine';
    public $requireOriginator = true;

    public function getUrl()
    {
        return $this->source->getUrl(true);
    }

    public function getMailSubject()
    {
        return Yii::t('VereineModule.base', 'Du verwaltest den Verein {verein} nicht mehr', ['verein' => $this->source->name]);
    }

    public function html()
    {
        return Yii::t('VereineModule.base', '{name} hat dich als Verwalter von {verein} entfernt. Falls das nicht abgesprochen war, melde dich bitte bei uns: {mail}', [
            'name' => Html::tag('strong', Html::encode($this->originator->displayName)),
            'verein' => Html::tag('strong', Html::encode($this->source->name)),
            'mail' => Html::encode(Yii::$app->getModule($this->moduleId)->kontaktMail)]);
    }
}
