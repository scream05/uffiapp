<?php

namespace humhub\modules\vereine\controllers;

use humhub\modules\content\components\ContentContainerController;
use humhub\modules\vereine\components\Bfv;
use humhub\modules\vereine\models\Verein;
use humhub\modules\vereine\models\VereinMannschaft;
use humhub\modules\vereine\models\VereinSpiel;
use humhub\modules\vereine\widgets\SpieltagKasten;
use Yii;
use yii\web\NotFoundHttpException;

/**
 * Spiele & Ergebnisse eines Fußballvereins (Daten vom BFV, siehe components/Bfv.php), auch für Gäste.
 * Laufende Spiele lädt die Seite jede Minute neu (?teil=live), den Kasten auf der Startseite ebenso (?teil=heute).
 */
class SpieleController extends ContentContainerController
{
    /** Mit Mannschaftsfilter: so viele frühere Spiele unter dem letzten */
    public const FRUEHERE = 30;

    public function actionIndex($verein = null, $mannschaft = null, $teil = null)
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
        $ids = $auswahl ? [$auswahl->id] : (array_map(fn($m) => $m->id, $mannschaften) ?: ['-']);
        $label = [];
        foreach ($mannschaften as $m) {
            $label[$m->id] = $m->getLabel();
        }

        // Laufende Spiele (gestern mitnehmen: Spiele über Mitternacht gibt es nicht, aber die Zeitzone des Kontos kann abweichen)
        $live = array_values(array_filter(VereinSpiel::find()->where(['mannschaft_id' => $ids])
            ->andWhere(['between', 'datum', date('Y-m-d', strtotime('-1 day')), date('Y-m-d')])
            ->orderBy(['datum' => SORT_ASC, 'uhrzeit' => SORT_ASC])->all(), fn(VereinSpiel $s) => $s->laeuft()));
        if ($teil === 'live') {
            return $this->renderPartial('_live', ['live' => $live, 'label' => $label, 'verein' => $verein, 'auswahl' => $auswahl]);
        }
        if ($teil === 'heute') {
            return SpieltagKasten::widget(); // Kasten auf der Startseite
        }

        // Nur das nächste Spiel je Mannschaft (abgesetzte übersprungen)
        $kommend = [];
        foreach ($auswahl ? [$auswahl] : $mannschaften as $m) {
            foreach (VereinSpiel::find()->where(['mannschaft_id' => $m->id])->andWhere(['>=', 'datum', date('Y-m-d', strtotime('-1 day'))])
                         ->orderBy(['datum' => SORT_ASC, 'uhrzeit' => SORT_ASC])->limit(20)->all() as $s) {
                if (!$s->istVorbei() && !$s->laeuft() && $s->getZustand() === null) {
                    $kommend[] = $s;
                    break;
                }
            }
        }
        usort($kommend, fn($a, $b) => $a->getZeitpunkt() <=> $b->getZeitpunkt());

        // Letztes Spiel je Mannschaft (mit Spielbericht), bei gewählter Mannschaft darunter die früheren
        $letzte = [];
        $fruehere = [];
        foreach ($auswahl ? [$auswahl] : $mannschaften as $m) {
            $vorbei = array_values(array_filter(VereinSpiel::find()->where(['mannschaft_id' => $m->id])->andWhere(['<=', 'datum', date('Y-m-d')])
                ->orderBy(['datum' => SORT_DESC, 'uhrzeit' => SORT_DESC])->limit(self::FRUEHERE + 5)->all(), fn(VereinSpiel $s) => $s->istVorbei()));
            // Als „letztes Spiel“ das letzte gespielte (abgesetzte stehen bei den früheren)
            foreach ($vorbei as $i => $s) {
                if ($s->getZustand() === null) {
                    $letzte[] = $s;
                    unset($vorbei[$i]);
                    $vorbei = array_values($vorbei);
                    break;
                }
            }
            $fruehere = array_slice($vorbei, 0, self::FRUEHERE); // nur bei gewählter Mannschaft angezeigt
        }
        usort($letzte, fn($a, $b) => $b->getZeitpunkt() <=> $a->getZeitpunkt());

        return $this->render('index', [
            'verein' => $verein,
            'mannschaften' => $mannschaften,
            'label' => $label,
            'auswahl' => $auswahl,
            'live' => $live,
            'kommend' => $kommend,
            'letzte' => $letzte,
            'fruehere' => $auswahl ? $fruehere : [],
            'stand' => Bfv::stand($verein),
        ]);
    }
}
