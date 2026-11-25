<?php

namespace humhub\modules\gewerbe\notifications;

use humhub\helpers\Html;
use humhub\modules\gewerbe\models\Betrieb;
use humhub\modules\notification\components\BaseNotification;
use Yii;

/**
 * An den Nutzer: ein anderer Verwalter hat ihn als Verwalter entfernt (damit niemand still verdrängt wird).
 *
 * @property Betrieb $source
 */
class VerwaltungEntfernt extends BaseNotification
{
    public $moduleId = 'gewerbe';
    public $requireOriginator = true;

    public function getUrl()
    {
        return $this->source->getUrl(true);
    }

    public function getMailSubject()
    {
        return Yii::t('GewerbeModule.base', 'Du verwaltest den Betrieb {betrieb} nicht mehr', ['betrieb' => $this->source->name]);
    }

    public function html()
    {
        return Yii::t('GewerbeModule.base', '{name} hat dich als Verwalter von {betrieb} entfernt. Falls das nicht abgesprochen war, melde dich bitte bei uns: {mail}', [
            'name' => Html::tag('strong', Html::encode($this->originator->displayName)),
            'betrieb' => Html::tag('strong', Html::encode($this->source->name)),
            'mail' => Html::encode(Yii::$app->getModule($this->moduleId)->kontaktMail)]);
    }
}
