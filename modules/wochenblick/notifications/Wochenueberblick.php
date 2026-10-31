<?php

namespace humhub\modules\wochenblick\notifications;

use humhub\modules\notification\components\BaseNotification;
use humhub\modules\wochenblick\components\Ueberblick;
use Yii;
use yii\helpers\Html;

/**
 * „Dein Wochenüberblick: 5 Termine · Abfuhr: Mo Restmüll · …“ – Daten im payload: kurz (Text in der Sprache
 * des Empfängers, beim Versand gebildet), von (Samstag des Zeitraums)
 */
class Wochenueberblick extends BaseNotification
{
    public $moduleId = 'wochenblick';
    public $requireOriginator = false;
    public $requireSource = false;

    public function category()
    {
        return new WochenblickCategory();
    }

    /** HumHub reiht Benachrichtigungen in die Warteschlange und serialisiert dabei nur Quelle/Absender – Daten mitnehmen */
    public function __serialize(): array
    {
        return parent::__serialize() + ['wochenblick' => $this->payload];
    }

    public function __unserialize($data)
    {
        parent::__unserialize($data);
        if (!empty($data['wochenblick'])) {
            $this->payload = $data['wochenblick'];
        }
    }

    private function data(): array
    {
        $p = $this->payload;
        if (!$p && !empty($this->record->payload)) {
            $p = json_decode($this->record->payload, true);
        }
        return is_array($p) ? $p : [];
    }

    public function text()
    {
        $d = $this->data();
        return Yii::t('WochenblickModule.base', 'Dein Wochenüberblick') . (empty($d['kurz']) ? '' : ': ' . $d['kurz']);
    }

    public function html()
    {
        $d = $this->data();
        return Html::tag('strong', Html::encode(Yii::t('WochenblickModule.base', 'Dein Wochenüberblick')))
            . (empty($d['kurz']) ? '' : ': ' . Html::encode($d['kurz']));
    }

    public function getMailSubject()
    {
        return Yii::t('WochenblickModule.base', 'Dein Wochenüberblick');
    }

    public function getUrl()
    {
        return Ueberblick::url();
    }
}
