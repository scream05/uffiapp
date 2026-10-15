<?php

namespace humhub\modules\gewerbe\notifications;

use humhub\helpers\Html;
use humhub\modules\gewerbe\models\Betrieb;
use humhub\modules\notification\components\BaseNotification;

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
        return Html::tag('strong', Html::encode($this->originator->displayName)) . ' hat bestätigt: Du kannst jetzt die Seite von '
            . Html::tag('strong', Html::encode($this->source->name)) . ' im Gewerbeverzeichnis pflegen.';
    }
}
