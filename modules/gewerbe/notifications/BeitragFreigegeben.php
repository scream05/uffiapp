<?php

namespace humhub\modules\gewerbe\notifications;

use humhub\helpers\Html;
use humhub\modules\notification\components\BaseNotification;
use Yii;
use humhub\modules\gewerbe\models\BetriebBeitrag;

/**
 * An den Verfasser: der Gewerbebeitrag ist veröffentlicht.
 *
 * @property BetriebBeitrag $source
 */
class BeitragFreigegeben extends BaseNotification
{
    public $moduleId = 'gewerbe';
    public $requireOriginator = false;

    public function getUrl()
    {
        if ($this->source->content) {
            return \yii\helpers\Url::to($this->source->content->getUrl(), true);
        }
        return $this->source->betrieb->getUrl(true);
    }

    public function getMailSubject()
    {
        return "Dein Beitrag für {$this->source->betrieb->name} ist veröffentlicht";
    }

    public function html()
    {
        return Yii::t('GewerbeModule.base', 'Dein Beitrag für {betrieb} ist jetzt veröffentlicht.', ['betrieb' => Html::tag('strong', Html::encode($this->source->betrieb->name))]);
    }
}
