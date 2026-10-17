<?php

namespace humhub\modules\gewerbe\notifications;

use humhub\helpers\Html;
use humhub\modules\gewerbe\models\Gutschein;
use humhub\modules\notification\components\BaseNotification;
use Yii;

/**
 * An alle Konten: neuer Gutschein eines Betriebs (Kategorie „Neue Gutscheine“, abschaltbar).
 *
 * @property Gutschein $source
 */
class GutscheinFuerAlle extends BaseNotification
{
    public $moduleId = 'gewerbe';
    public $requireOriginator = false;

    public function category()
    {
        return new GutscheinCategory();
    }

    public function getUrl()
    {
        return $this->source->getUrl(true);
    }

    public function getMailSubject()
    {
        return "Neuer Gutschein: {$this->source->titel}";
    }

    public function html()
    {
        $g = $this->source;
        $ab = $g->gueltig_ab > date('Y-m-d') ? ' (ab ' . Yii::$app->formatter->asDate($g->gueltig_ab, 'php:j.n.') . ')' : '';
        return 'Neuer Gutschein von ' . Html::tag('strong', Html::encode($g->betrieb->name)) . ': „'
            . Html::encode($g->titel) . '“' . $ab;
    }
}
