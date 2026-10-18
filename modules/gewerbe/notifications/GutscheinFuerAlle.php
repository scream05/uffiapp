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
        return Yii::t('GewerbeModule.base', 'Neuer Gutschein: {titel}', ['titel' => $this->source->titel]);
    }

    public function html()
    {
        $g = $this->source;
        $params = ['betrieb' => Html::tag('strong', Html::encode($g->betrieb->name)), 'titel' => Html::encode($g->titel)];
        if ($g->gueltig_ab > date('Y-m-d')) {
            $params['datum'] = Yii::$app->formatter->asDate($g->gueltig_ab, str_starts_with(Yii::$app->language, 'de') ? 'php:j.n.' : 'medium');
            return Yii::t('GewerbeModule.base', 'Neuer Gutschein von {betrieb}: „{titel}“ (ab {datum})', $params);
        }
        return Yii::t('GewerbeModule.base', 'Neuer Gutschein von {betrieb}: „{titel}“', $params);
    }
}
