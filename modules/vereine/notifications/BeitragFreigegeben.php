<?php

namespace humhub\modules\vereine\notifications;

use humhub\helpers\Html;
use humhub\modules\notification\components\BaseNotification;
use humhub\modules\vereine\models\VereinBeitrag;

/**
 * An den Verfasser: der Vereinsbeitrag ist veröffentlicht.
 *
 * @property VereinBeitrag $source
 */
class BeitragFreigegeben extends BaseNotification
{
    public $moduleId = 'vereine';
    public $requireOriginator = false;

    public function getUrl()
    {
        if ($this->source->content) {
            return \yii\helpers\Url::to($this->source->content->getUrl(), true);
        }
        return $this->source->verein->getUrl(true);
    }

    public function getMailSubject()
    {
        return "Ihr Beitrag für {$this->source->verein->name} ist veröffentlicht";
    }

    public function html()
    {
        return 'Ihr Beitrag für ' . Html::tag('strong', Html::encode($this->source->verein->name)) . ' ist jetzt veröffentlicht.';
    }
}
