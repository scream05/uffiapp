<?php

namespace humhub\modules\vereine\notifications;

use humhub\helpers\Html;
use humhub\modules\notification\components\BaseNotification;
use humhub\modules\vereine\models\Verein;

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
        return "Sie können jetzt den Verein {$this->source->name} verwalten";
    }

    public function html()
    {
        return Html::tag('strong', Html::encode($this->originator->displayName)) . ' hat bestätigt: Sie können jetzt den Verein '
            . Html::tag('strong', Html::encode($this->source->name)) . ' verwalten (Vorstellungsseite und Termine).';
    }
}
