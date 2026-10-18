<?php

namespace humhub\modules\vereine\widgets;

use humhub\components\Widget;
use humhub\helpers\Html;
use humhub\modules\calendar\models\CalendarEntry;
use humhub\modules\vereine\models\Verein;
use humhub\modules\vereine\models\VereinBeitrag;
use Yii;

class VereinAddon extends Widget
{
    public ?CalendarEntry $entry = null;
    public ?VereinBeitrag $beitrag = null;

    public function run()
    {
        if ($this->beitrag !== null) {
            $verein = $this->beitrag->verein;
            return $verein ? Html::tag('div', '<i class="fa fa-users"></i> ' . Yii::t('VereineModule.base', 'Beitrag von {verein}', ['verein' => Html::a(Html::encode($verein->name), $verein->getUrl())]),
                ['class' => 'verein-addon', 'style' => 'margin:8px 0 4px;font-size:0.95em']) : '';
        }
        $verein = Verein::forEntry($this->entry);
        if ($verein === null) {
            return '';
        }
        $space = Yii::$app->getModule('vereine')->getDirectorySpace();
        $html = '<i class="fa fa-users"></i> ' . Yii::t('VereineModule.base', 'Veranstalter: {verein}', ['verein' => Html::a(Html::encode($verein->name), $verein->getUrl())]);
        if ($space && $verein->canManage()) {
            $html .= ' · ' . Html::a('<i class="fa fa-pencil"></i> ' . Yii::t('VereineModule.base', 'Termin bearbeiten'),
                    $space->createUrl('/vereine/termin/edit', ['id' => $this->entry->id]));
        }
        return Html::tag('div', $html, ['class' => 'verein-addon', 'style' => 'margin:8px 0 4px;font-size:0.95em']);
    }
}
