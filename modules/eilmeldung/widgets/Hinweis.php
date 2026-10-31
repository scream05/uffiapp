<?php

namespace humhub\modules\eilmeldung\widgets;

use humhub\components\Widget;
use humhub\modules\eilmeldung\components\Versand;

/** Laufende Eilmeldungen als auffälliger Kasten (Startseite) */
class Hinweis extends Widget
{
    public function run()
    {
        $liste = Versand::aktive();
        return $liste ? $this->render('hinweis', ['liste' => $liste, 'module' => Versand::module()]) : '';
    }
}
