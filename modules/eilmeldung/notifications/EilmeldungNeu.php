<?php

namespace humhub\modules\eilmeldung\notifications;

use humhub\modules\notification\components\BaseNotification;
use Yii;
use yii\helpers\Html;
use yii\helpers\Url;

/** „Eilmeldung – Straßensperrung: …“ – Daten im payload: id, art, titel */
class EilmeldungNeu extends BaseNotification
{
    public $moduleId = 'eilmeldung';
    public $requireOriginator = false;
    public $requireSource = false;

    public function category()
    {
        return new EilmeldungCategory();
    }

    /** HumHub reiht Benachrichtigungen in die Warteschlange und serialisiert dabei nur Quelle/Absender – Daten mitnehmen */
    public function __serialize(): array
    {
        return parent::__serialize() + ['eilmeldung' => $this->payload];
    }

    public function __unserialize($data)
    {
        parent::__unserialize($data);
        if (!empty($data['eilmeldung'])) {
            $this->payload = $data['eilmeldung'];
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
        return '⚠️ ' . Yii::t('EilmeldungModule.base', 'Eilmeldung') . ' – ' . Yii::$app->getModule('eilmeldung')->artName($d['art'] ?? '') . ': ' . ($d['titel'] ?? '');
    }

    public function html()
    {
        $d = $this->data();
        return Html::tag('strong', Html::encode(Yii::t('EilmeldungModule.base', 'Eilmeldung') . ' – ' . Yii::$app->getModule('eilmeldung')->artName($d['art'] ?? '')))
            . ': ' . Html::encode($d['titel'] ?? '');
    }

    public function getMailSubject()
    {
        return $this->text();
    }

    public function getUrl()
    {
        $d = $this->data();
        return Url::to(['/eilmeldung/index', '#' => 'eilmeldung-' . ($d['id'] ?? '')], true);
    }
}
