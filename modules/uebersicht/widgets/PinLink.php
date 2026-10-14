<?php

namespace humhub\modules\uebersicht\widgets;

use humhub\components\Widget;
use humhub\modules\content\components\ContentActiveRecord;
use humhub\widgets\bootstrap\Button;
use yii\helpers\Html;
use yii\helpers\Url;
use Yii;

/** Menüeintrag im Beitragsmenü (nur Admins) */
class PinLink extends Widget
{
    public ContentActiveRecord $record;

    public function run()
    {
        $id = $this->record->content->id;
        $an = Yii::$app->getModule('uebersicht')->istAngepinnt($id);
        $link = Button::asLink($an ? 'Aus Übersicht lösen' : 'In Übersicht anpinnen')
            ->action('uebersicht.umschalten', Url::to(['/uebersicht/pin/umschalten', 'id' => $id]))
            ->cssClass('dropdown-item')
            ->icon('thumb-tack');
        return Html::tag('li', $link);
    }
}
