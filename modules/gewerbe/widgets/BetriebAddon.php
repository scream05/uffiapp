<?php

namespace humhub\modules\gewerbe\widgets;

use humhub\components\Widget;
use humhub\helpers\Html;
use humhub\modules\gewerbe\models\BetriebBeitrag;
use Yii;

/** „Beitrag von <Betrieb>“ unter einem freigegebenen Gewerbebeitrag in der Übersicht */
class BetriebAddon extends Widget
{
    public ?BetriebBeitrag $beitrag = null;

    public function run()
    {
        $betrieb = $this->beitrag?->betrieb;
        return $betrieb ? Html::tag('div', '<i class="fa fa-shopping-bag"></i> ' . Yii::t('GewerbeModule.base', 'Beitrag von {betrieb}', ['betrieb' => Html::a(Html::encode($betrieb->name), $betrieb->getUrl())]),
            ['class' => 'gewerbe-addon', 'style' => 'margin:8px 0 4px;font-size:0.95em']) : '';
    }
}
