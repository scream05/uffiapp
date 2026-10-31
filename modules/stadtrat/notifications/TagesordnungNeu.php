<?php

namespace humhub\modules\stadtrat\notifications;

use humhub\modules\notification\components\BaseNotification;
use humhub\modules\stadtrat\components\Ris;
use humhub\modules\stadtrat\models\Sitzung;
use Yii;
use yii\helpers\Html;

/** „Tagesordnung veröffentlicht: Stadtrat am Do., 19.11.2026 (6 Punkte)“ – Daten im payload: id, gremium, tag, top */
class TagesordnungNeu extends BaseNotification
{
    public $moduleId = 'stadtrat';
    public $requireOriginator = false;
    public $requireSource = false;

    public function category()
    {
        return new StadtratCategory();
    }

    /** HumHub reiht Benachrichtigungen in die Warteschlange und serialisiert dabei nur Quelle/Absender – Daten mitnehmen */
    public function __serialize(): array
    {
        return parent::__serialize() + ['stadtrat' => $this->payload];
    }

    public function __unserialize($data)
    {
        parent::__unserialize($data);
        if (!empty($data['stadtrat'])) {
            $this->payload = $data['stadtrat'];
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

    private function satz(): array
    {
        $d = $this->data();
        return [Yii::t('StadtratModule.base', 'Tagesordnung veröffentlicht'), Yii::t('StadtratModule.base', '{gremium} am {tag} ({n, plural, =1{1 Punkt} other{# Punkte}})', [
            'gremium' => $d['gremium'] ?? '', 'tag' => $d['tag'] ?? '', 'n' => (int)($d['top'] ?? 0)])];
    }

    public function text()
    {
        [$a, $b] = $this->satz();
        return "🏛️ $a: $b";
    }

    public function html()
    {
        [$a, $b] = $this->satz();
        return Html::tag('strong', Html::encode($a)) . ': ' . Html::encode($b);
    }

    public function getMailSubject()
    {
        return $this->text();
    }

    public function getUrl()
    {
        $s = Sitzung::findOne((int)($this->data()['id'] ?? 0));
        return Ris::url($s, true);
    }
}
