<?php

namespace humhub\modules\tanken\widgets;

use humhub\components\Widget;
use humhub\modules\tanken\components\Spritpreise;
use Yii;

/** Kasten auf der Startseite: alle Tankstellen in Uffenheim (5 km) mit E5, E10 und Diesel */
class SpritKasten extends Widget
{
    public function run()
    {
        $daten = Spritpreise::daten();
        if ($daten === null) {
            return '';
        }
        $liste = Spritpreise::liste($daten, Yii::$app->getModule('tanken')->kastenRadius, 'e5', false);
        return $liste ? $this->render('kasten', ['daten' => $daten, 'liste' => $liste]) : '';
    }
}
