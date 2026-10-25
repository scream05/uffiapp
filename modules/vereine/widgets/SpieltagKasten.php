<?php

namespace humhub\modules\vereine\widgets;

use humhub\components\Widget;
use humhub\modules\vereine\models\Verein;
use humhub\modules\vereine\models\VereinMannschaft;
use humhub\modules\vereine\models\VereinSpiel;

/** Kasten auf der Startseite an Spieltagen: heutige Spiele aller Sportarten mit Anstoß, Live-Stand (Fußball) bzw. Ergebnis */
class SpieltagKasten extends Widget
{
    /** So viele Zeilen sichtbar, der Rest per „+ n weitere“ aufklappbar; 0 = alle (Seite „Sport“) */
    public int $zeilen = 3;

    public function run()
    {
        $vereine = Verein::find()->where(['not', ['bfv_id' => null]])->orderBy(\humhub\modules\vereine\components\Spielquellen::reihenfolge())->all();
        $label = [];
        $verein = []; // Mannschaft-ID => Verein (für Link und Sportart)
        foreach ($vereine as $v) {
            foreach (VereinMannschaft::sortieren($v->mannschaften) as $m) {
                $label[$m->id] = (count($vereine) > 1 ? ($v->bfv_name ?: $v->name) . ' ' : '') . $m->getLabel();
                $verein[$m->id] = $v;
            }
        }
        // Abgesetzte Spiele stehen mit drin, damit niemand umsonst hinfährt
        $spiele = $label ? VereinSpiel::find()->where(['mannschaft_id' => array_keys($label), 'datum' => date('Y-m-d')])
            ->orderBy(['uhrzeit' => SORT_ASC])->all() : [];
        if (!$spiele || $vereine[0]->getSpieleUrl() === null) {
            return '';
        }
        return $this->render('spieltag', ['spiele' => $spiele, 'label' => $label, 'verein' => $vereine[0], 'vereinVon' => $verein, 'zeilen' => $this->zeilen]);
    }
}
