<?php

namespace humhub\modules\maengelmelder\notifications;

use humhub\modules\maengelmelder\models\MaengelKontakt;
use humhub\modules\notification\components\BaseNotification;
use humhub\modules\space\models\Space;
use Yii;
use yii\helpers\Html;

/**
 * An die Bauhofleitung: neue Mängelmeldung ohne Konto wartet auf Freigabe.
 * Quelle sind die Kontaktdaten (nicht der Beitrag, der als Entwurf für HumHub nicht lesbar ist);
 * wird die Meldung abgelehnt, verschwindet die Quelle und mit ihr die Benachrichtigung.
 *
 * @property MaengelKontakt $source
 */
class GastmeldungNeu extends BaseNotification
{
    public $moduleId = 'maengelmelder';
    public $requireOriginator = false;

    public function category()
    {
        return new GastmeldungCategory();
    }

    public function text()
    {
        $mangel = \humhub\modules\maengelmelder\models\Mangel::fromContent(
            \humhub\modules\content\models\Content::findOne($this->source->content_id));
        return "Neue Gastmeldung Nr. {$this->source->content_id}" . ($mangel ? " ({$mangel->kategorie})" : '') . ' wartet auf Freigabe.';
    }

    public function html()
    {
        return Html::encode($this->text());
    }

    public function getMailSubject()
    {
        return $this->text();
    }

    public function getUrl()
    {
        $space = Space::findOne(['url' => Yii::$app->getModule('maengelmelder')->bauhofSpace]);
        return $space
            ? $space->createUrl('/maengelmelder/bauhof/mangel', ['id' => $this->source->content_id], true)
            : parent::getUrl();
    }
}
