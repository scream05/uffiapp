<?php

namespace humhub\modules\vereine\controllers;

use humhub\modules\content\components\ContentContainerController;
use humhub\modules\vereine\components\Bfv;
use humhub\modules\vereine\models\Verein;
use humhub\modules\vereine\models\VereinMannschaft;
use humhub\modules\vereine\models\VereinSpiel;
use Yii;
use yii\web\NotFoundHttpException;

/**
 * Spiele & Ergebnisse eines Fußballvereins (Daten vom BFV, siehe components/Bfv.php), auch für Gäste.
 */
class SpieleController extends ContentContainerController
{
    /** Ohne Mannschaftsfilter: Ergebnisse und Spiele so viele Tage zurück bzw. voraus */
    public const TAGE_ZURUECK = 21;
    public const TAGE_VORAUS = 35;

    public function actionIndex($verein = null, $mannschaft = null)
    {
        $query = Verein::find()->where(['not', ['bfv_id' => null]]);
        $verein = $verein !== null ? $query->andWhere(['id' => (int)$verein])->one() : $query->orderBy(['name' => SORT_ASC])->one();
        if ($verein === null) {
            throw new NotFoundHttpException(Yii::t('VereineModule.base', 'Für diesen Verein gibt es keine Spiele.'));
        }
        $mannschaften = VereinMannschaft::sortieren($verein->mannschaften);
        $auswahl = null;
        foreach ($mannschaften as $m) {
            if ($m->id === $mannschaft) {
                $auswahl = $m;
            }
        }

        $spiele = VereinSpiel::find()->where(['mannschaft_id' => $auswahl ? $auswahl->id : (array_map(fn($m) => $m->id, $mannschaften) ?: ['-'])])
            ->orderBy(['datum' => SORT_ASC, 'uhrzeit' => SORT_ASC]);
        if ($auswahl === null) {
            $spiele->andWhere(['between', 'datum', date('Y-m-d', strtotime('-' . self::TAGE_ZURUECK . ' days')), date('Y-m-d', strtotime('+' . self::TAGE_VORAUS . ' days'))]);
        }
        $kommend = $ergebnisse = [];
        foreach ($spiele->all() as $s) {
            if ($s->istVorbei()) {
                array_unshift($ergebnisse, $s); // neueste zuerst
            } else {
                $kommend[] = $s;
            }
        }

        return $this->render('index', [
            'verein' => $verein,
            'mannschaften' => $mannschaften,
            'auswahl' => $auswahl,
            'kommend' => $kommend,
            'ergebnisse' => $ergebnisse,
            'stand' => Bfv::stand($verein),
        ]);
    }
}
