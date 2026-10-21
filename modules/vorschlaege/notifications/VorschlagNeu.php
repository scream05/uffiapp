<?php

namespace humhub\modules\vorschlaege\notifications;

use humhub\helpers\Html;
use humhub\modules\notification\components\BaseNotification;
use humhub\modules\vorschlaege\models\Vorschlag;
use Yii;

/**
 * An Admins / „Vorschläge bearbeiten“: jemand hat einen neuen Vorschlag eingereicht.
 *
 * @property \humhub\modules\post\models\Post $source
 */
class VorschlagNeu extends BaseNotification
{
    public $moduleId = 'vorschlaege';
    public $requireOriginator = true;

    private function titel(): string
    {
        return Vorschlag::findOne(['content_id' => $this->source->content->id])->titel ?? '';
    }

    public function getMailSubject()
    {
        return Yii::t('VorschlaegeModule.base', 'Neuer Vorschlag von {name}: {titel}', ['name' => $this->originator->displayName, 'titel' => $this->titel()]);
    }

    public function html()
    {
        return Yii::t('VorschlaegeModule.base', '{name} hat einen neuen Vorschlag eingereicht: {titel}', [
            'name' => Html::tag('strong', Html::encode($this->originator->displayName)),
            'titel' => Html::tag('strong', Html::encode($this->titel()))]);
    }
}
