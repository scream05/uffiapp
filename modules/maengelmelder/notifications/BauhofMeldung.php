<?php

namespace humhub\modules\maengelmelder\notifications;

use humhub\modules\maengelmelder\components\Bauhof;
use humhub\modules\maengelmelder\models\BauhofNachricht;
use humhub\modules\notification\components\BaseNotification;
use humhub\modules\space\models\Space;
use Yii;
use yii\helpers\Html;

/**
 * An die Bauhofleitung: ein Mitarbeiter hat per WhatsApp geschrieben, ein Foto geschickt oder den Status geändert.
 *
 * @property BauhofNachricht $source
 */
class BauhofMeldung extends BaseNotification
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
        if ($n->buerger) {
            return "Bauhof: Die meldende Person hat zu Nr. {$n->content_id} " . ($n->file_id || $n->media_id ? 'ein Foto geschickt.'
                : 'geschrieben: „' . mb_strimwidth((string)$n->text, 0, 80, '…') . '“');
        }
        $wer = $n->mitarbeiter?->name ?? 'Ein Mitarbeiter';
        $nr = $n->content_id ? " zu Auftrag Nr. {$n->content_id}" : '';
        if ($n->typ === 'knopf' && preg_match('/^s:(\d+):(\w+)$/', (string)$n->text, $m)) {
            return "Bauhof: $wer hat Auftrag Nr. {$m[1]} auf „" . (Bauhof::STATUS_KURZ[$m[2]] ?? $m[2]) . '“ gesetzt.';
        }
        if (in_array($n->typ, ['image', 'document'], true)) {
            return "Bauhof: $wer hat ein Foto$nr geschickt.";
        }
        return "Bauhof: $wer hat$nr geschrieben: „" . mb_strimwidth((string)$n->text, 0, 80, '…') . '“';
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
