<?php

namespace humhub\modules\uebersicht\filters;

use humhub\modules\stream\models\filters\StreamQueryFilter;
use Yii;

/** Übersicht der Gäste: keine Beiträge aus Bereichen, die standardmäßig aus sind (Module::$standardAus) */
class GastStandardAus extends StreamQueryFilter
{
    public function apply()
    {
        if (!Yii::$app->user->isGuest || $this->streamQuery->isSingleContentQuery()) {
            return;
        }
        if ($ids = Yii::$app->getModule('uebersicht')->standardAusContainerIds()) {
            $this->query->andWhere(['or', ['content.contentcontainer_id' => null], ['not in', 'content.contentcontainer_id', $ids]]);
        }
    }
}
