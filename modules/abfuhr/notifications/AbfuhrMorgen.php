<?php

namespace humhub\modules\abfuhr\notifications;

use humhub\modules\abfuhr\components\Erinnerung;
use humhub\modules\notification\components\BaseNotification;
use Yii;
use yii\helpers\Html;
use yii\helpers\Url;

/**
 * „Morgen: Biotonne und Restmüll“ – Daten im payload: tag (Y-m-d), tonnen (Schlüssel), heute (bool)
 */
class AbfuhrMorgen extends BaseNotification
{
    public $moduleId = 'abfuhr';
    public $requireOriginator = false;
    public $requireSource = false;

    public function category()
    {
        return new AbfuhrCategory();
    }

    /** HumHub reiht Benachrichtigungen in die Warteschlange und serialisiert dabei nur Quelle/Absender – Daten mitnehmen */
    public function __serialize(): array
    {
        return parent::__serialize() + ['abfuhr' => $this->payload];
    }

    public function __unserialize($data)
    {
        parent::__unserialize($data);
        if (!empty($data['abfuhr'])) {
            $this->payload = $data['abfuhr'];
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
        if (empty($d['tag'])) {
            return 'Abfuhr-Erinnerung';
        }
        $wann = !empty($d['heute']) ? 'Heute' : 'Morgen';
        // Wochentag fest auf Deutsch: Push und E-Mail entstehen im Hintergrund (Konsole, ggf. andere Sprache)
        $datum = Erinnerung::tag($d['tag']);
        return "$wann ($datum): " . Erinnerung::liste($d['tonnen'] ?? []) . ' – bitte rechtzeitig bereitstellen.';
    }

    /** Im Browser (Glocke, Benachrichtigungsliste) in der Sprache des Nutzers, im Hintergrund (Push, E-Mail) wie text() */
    public function html()
    {
        $d = $this->data();
        if (!(Yii::$app instanceof \yii\web\Application)) {
            return Html::encode($this->text());
        }
        if (empty($d['tag'])) {
            return Html::encode(Yii::t('AbfuhrModule.base', 'Abfuhr-Erinnerung'));
        }
        $params = ['datum' => Erinnerung::tag($d['tag'], true), 'tonnen' => Erinnerung::liste($d['tonnen'] ?? [], true)];
        return Html::encode(!empty($d['heute'])
            ? Yii::t('AbfuhrModule.base', 'Heute ({datum}): {tonnen} – bitte rechtzeitig bereitstellen.', $params)
            : Yii::t('AbfuhrModule.base', 'Morgen ({datum}): {tonnen} – bitte rechtzeitig bereitstellen.', $params));
    }

    public function getMailSubject()
    {
        return $this->text();
    }

    public function getUrl()
    {
        $space = Erinnerung::space();
        return $space ? $space->createUrl('/calendar/view/index', [], true) : Url::to(['/abfuhr/einstellungen'], true);
    }
}
