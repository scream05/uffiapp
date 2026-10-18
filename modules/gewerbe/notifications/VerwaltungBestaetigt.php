<?php

namespace humhub\modules\gewerbe\notifications;

use humhub\helpers\Html;
use humhub\modules\gewerbe\models\Betrieb;
use humhub\modules\notification\components\BaseNotification;
use Yii;

/**
 * An den Nutzer: Verwaltung eines Betriebs bestätigt.
 *
 * @property Betrieb $source
 */
class VerwaltungBestaetigt extends BaseNotification
{
    public $moduleId = 'gewerbe';
    public $requireOriginator = true;

    public function getUrl()
    {
        return $this->source->getUrl(true);
    }

    public function getMailSubject()
    {
        return "Du kannst jetzt den Betrieb {$this->source->name} verwalten";
    }

    public function html()
    {
        return Yii::t('GewerbeModule.base', '{name} hat bestätigt: Du kannst jetzt die Seite von {betrieb} im Gewerbeverzeichnis pflegen.', [
            'name' => Html::tag('strong', Html::encode($this->originator->displayName)), 'betrieb' => Html::tag('strong', Html::encode($this->source->name))]);
    }
}
