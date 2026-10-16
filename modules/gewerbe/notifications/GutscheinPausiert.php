<?php

namespace humhub\modules\gewerbe\notifications;

use humhub\helpers\Html;
use humhub\modules\gewerbe\models\Gutschein;
use humhub\modules\notification\components\BaseNotification;

/**
 * An die Verwalter des Betriebs und Admins: ein Gutschein wurde wegen auffällig vieler Einlösungen pausiert.
 *
 * @property Gutschein $source
 */
class GutscheinPausiert extends BaseNotification
{
    public $moduleId = 'gewerbe';
    public $requireOriginator = false;

    public function getUrl()
    {
        $space = \Yii::$app->getModule('gewerbe')->getDirectorySpace();
        return $space ? $space->createUrl('/gewerbe/gutschein/verwaltung', ['betrieb' => $this->source->betrieb_id], true) : parent::getUrl();
    }

    public function getMailSubject()
    {
        return "Gutschein von {$this->source->betrieb->name} automatisch pausiert";
    }

    public function html()
    {
        return 'Der Gutschein „' . Html::encode($this->source->titel) . '“ von ' . Html::tag('strong', Html::encode($this->source->betrieb->name))
            . ' wurde wegen auffällig vieler Einlösungen automatisch pausiert.';
    }
}
