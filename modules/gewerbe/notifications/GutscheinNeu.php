<?php

namespace humhub\modules\gewerbe\notifications;

use humhub\helpers\Html;
use humhub\modules\gewerbe\models\Gutschein;
use humhub\modules\notification\components\BaseNotification;
use Yii;

/**
 * An Admins / „Gewerbe verwalten“: ein Betrieb hat einen Gutschein veröffentlicht (ohne Prüfung, nur zur Kenntnis).
 *
 * @property Gutschein $source
 */
class GutscheinNeu extends BaseNotification
{
    public $moduleId = 'gewerbe';
    public $requireOriginator = false;

    public function getUrl()
    {
        return $this->source->getUrl(true);
    }

    public function getMailSubject()
    {
        return "Neuer Gutschein von {$this->source->betrieb->name}";
    }

    public function html()
    {
        return Yii::t('GewerbeModule.base', '{betrieb} hat einen Gutschein veröffentlicht: „{titel}“.', [
            'betrieb' => Html::tag('strong', Html::encode($this->source->betrieb->name)), 'titel' => Html::encode($this->source->titel)]);
    }
}
