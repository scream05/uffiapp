<?php

namespace humhub\modules\stadtrat\widgets;

use humhub\components\Widget;
use humhub\modules\stadtrat\models\Sitzung;
use Yii;

/** Kasten auf der Startseite: nächste Sitzung mit den ersten Tagesordnungspunkten */
class NaechsteSitzung extends Widget
{
    public function run()
    {
        if (Yii::$app->db->getTableSchema('stadtrat_sitzung') === null) {
            return '';
        }
        $s = Sitzung::kommende()->limit(1)->one();
        return $s ? $this->render('kasten', ['s' => $s]) : '';
    }
}
