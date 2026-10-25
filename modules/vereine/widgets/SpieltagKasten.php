<?php

namespace humhub\modules\vereine\widgets;

use humhub\components\Widget;
use humhub\modules\vereine\models\Verein;
use humhub\modules\vereine\models\VereinMannschaft;
use humhub\modules\vereine\models\VereinSpiel;

/** Kasten auf der Startseite an Spieltagen: heutige Spiele mit Anstoß, Live-Stand bzw. Ergebnis */
class SpieltagKasten extends Widget
{
    public function run()
    {
        $vereine = Verein::find()->where(['not', ['bfv_id' => null]])->orderBy(['name' => SORT_ASC])->all();
        $label = [];
        foreach ($vereine as $v) {
            foreach (VereinMannschaft::sortieren($v->mannschaften) as $m) {
                $label[$m->id] = (count($vereine) > 1 ? ($v->bfv_name ?: $v->name) . ' ' : '') . $m->getLabel();
            }
        }
        $spiele = $label ? array_values(array_filter(VereinSpiel::find()->where(['mannschaft_id' => array_keys($label), 'datum' => date('Y-m-d')])
            ->orderBy(['uhrzeit' => SORT_ASC])->all(), fn(VereinSpiel $s) => $s->getZustand() === null)) : [];
        if (!$spiele || $vereine[0]->getSpieleUrl() === null) {
            return '';
        }
        return $this->render('spieltag', ['spiele' => $spiele, 'label' => $label, 'verein' => $vereine[0]]);
    }
}
