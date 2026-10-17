<?php

namespace humhub\modules\maengelmelder\notifications;

use humhub\modules\maengelmelder\models\BauhofNachricht;
use humhub\modules\notification\components\BaseNotification;
use humhub\modules\space\models\Space;
use Yii;
use yii\helpers\Html;

/**
 * An die Bauhofleitung: Auftrag/Nachricht konnte nicht kostenlos per WhatsApp raus (Mitarbeiter seit über 24 Std. still,
 * Vorlagen aus) – bitte anrufen; alles Vorgemerkte kommt, sobald der Mitarbeiter wieder an die Nummer schreibt.
 *
 * @property BauhofNachricht $source
 */
class BauhofNichtZugestellt extends BaseNotification
{
    public $moduleId = 'maengelmelder';
    public $requireOriginator = false;

    public function category()
    {
        return new BauhofCategory();
    }

    public function text()
    {
        $n = $this->source;
        $m = $n->mitarbeiter;
        $was = $n->typ === 'auftrag' ? "Auftrag Nr. {$n->content_id}" : 'Eine Nachricht' . ($n->content_id ? " zu Auftrag Nr. {$n->content_id}" : '');
        $name = $m?->name ?? 'den Mitarbeiter';
        return "Bauhof: $was ist noch nicht bei $name angekommen (seit über 24 Std. keine WhatsApp von dort). Bitte anrufen"
            . ($m ? ' (' . $m->getTelefonAnzeige() . ')' : '') . ' – sobald wieder an die Bauhof-Nummer geschrieben wird, kommt alles automatisch.';
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
        if (!$space) {
            return parent::getUrl();
        }
        return $this->source->content_id
            ? $space->createUrl('/maengelmelder/bauhof/mangel', ['id' => $this->source->content_id], true)
            : $space->createUrl('/maengelmelder/bauhof/chat', ['id' => $this->source->mitarbeiter_id], true);
    }
}
