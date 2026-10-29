<?php

namespace humhub\modules\designwahl\widgets;

use humhub\components\Widget;
use humhub\modules\content\components\ContentActiveRecord;
use humhub\widgets\bootstrap\Link;
use yii\helpers\Html;
use yii\helpers\Url;
use Yii;

/** Beitragsmenü ⋯ → „Bildbeschreibungen“ (nur bei Beiträgen mit Bildern und für alle, die ihn bearbeiten dürfen) */
class BildtextLink extends Widget
{
    public ContentActiveRecord $record;

    public function run()
    {
        $link = Link::to(Yii::t('DesignwahlModule.base', 'Bildbeschreibungen'), Url::to(['/designwahl/bildtext/index', 'id' => $this->record->content->id]))
            ->cssClass('dropdown-item')
            ->icon('picture-o')
            ->pjax(false);
        return Html::tag('li', $link);
    }
}
