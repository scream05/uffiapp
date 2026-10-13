<?php

namespace humhub\modules\whatsapp\notifications;

use humhub\modules\notification\components\BaseNotification;
use humhub\modules\whatsapp\models\Entwurf;
use humhub\modules\whatsapp\models\Nachricht;
use Yii;
use yii\helpers\Html;

/**
 * An Admins: neue Einreichung aus der WhatsApp-Community wartet im Eingang.
 * Quelle ist der erste erkannte Termin (Entwurf) oder, wenn nichts erkannt wurde, die Nachricht.
 *
 * @property Entwurf|Nachricht $source
 */
class NeueEntwuerfe extends BaseNotification
{
    public $moduleId = 'whatsapp';
    public $requireOriginator = false;

    public function text()
    {
        if ($this->source instanceof Entwurf) {
            $weitere = Entwurf::find()->where(['gruppe' => $this->source->gruppe])->count() - 1;
            return 'WhatsApp-Community: „' . $this->source->titel . '“'
                . ($weitere > 0 ? " und $weitere weitere" : '') . ' wartet auf Freigabe.';
        }
        return 'WhatsApp-Community: Einreichung ohne erkannten Termin – bitte ansehen.';
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
        $space = Yii::$app->getModule('whatsapp')->getCalendarSpace();
        return $space ? $space->createUrl('/whatsapp/eingang/index', [], true) : parent::getUrl();
    }
}
