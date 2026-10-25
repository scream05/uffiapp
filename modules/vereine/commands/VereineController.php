<?php

namespace humhub\modules\vereine\commands;

use humhub\modules\vereine\components\Bfv;
use humhub\modules\vereine\models\Verein;
use Yii;
use yii\console\Controller;
use yii\console\ExitCode;

/**
 * Spiele der Fußballvereine vom BFV:
 *   /app/yii vereine/spiele        Abgleich (Crontab täglich 5:20)
 *   /app/yii vereine/live          Liveticker laufender Spiele (Crontab minütlich, ruft ohne laufendes Spiel nichts ab)
 *   /app/yii vereine/ticker <spiel-id>   Liveticker eines Spiels anzeigen (Prüfung, speichert nichts)
 *   /app/yii vereine/name-sperren "Vorname Name"   Namen nach Widerspruch aus allen Spielberichten entfernen (auch künftig)
 *   /app/yii vereine/bfv <verein-id> <bfv-kennung|->   Verein mit dem BFV verknüpfen bzw. lösen („-“)
 */
class VereineController extends Controller
{
    public function actionSpiele()
    {
        $fehler = false;
        foreach (Verein::find()->where(['not', ['bfv_id' => null]])->all() as $verein) {
            try {
                $log = Bfv::abrufen($verein);
            } catch (\Throwable $e) {
                $log = ['! ' . $e->getMessage()];
            }
            $fehler = $fehler || (bool)preg_grep('/^!/', $log);
            $this->stdout(date('Y-m-d H:i:s') . " {$verein->name}: " . implode('; ', $log) . "\n");
        }
        Yii::$app->cache->delete('ki-assistent-context');
        return $fehler ? ExitCode::UNSPECIFIED_ERROR : ExitCode::OK;
    }

    public function actionLive()
    {
        $log = Bfv::live();
        if ($log) {
            $this->stdout(date('Y-m-d H:i:s') . ' ' . implode('; ', $log) . "\n");
        }
        return preg_grep('/^!/', $log) ? ExitCode::UNSPECIFIED_ERROR : ExitCode::OK;
    }

    public function actionTicker($spielId, $mitNamen = 1)
    {
        $this->stdout(json_encode(Bfv::ticker($spielId, (bool)$mitNamen), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n");
        return ExitCode::OK;
    }

    public function actionNameSperren($name)
    {
        $name = trim($name);
        if (mb_strlen($name) < 3) {
            $this->stderr("Bitte den vollständigen Namen angeben\n");
            return ExitCode::DATAERR;
        }
        $liste = array_values(array_unique(array_merge(Bfv::gesperrteNamen(), [$name])));
        Yii::$app->getModule('vereine')->settings->set('tickerGesperrt', json_encode($liste, JSON_UNESCAPED_UNICODE));
        $n = 0;
        foreach (\humhub\modules\vereine\models\VereinSpiel::find()->where(['like', 'ticker', $name])->all() as $spiel) {
            $ereignisse = array_map(fn($e) => ['t' => str_ireplace($name, '–', $e['t']), 'k' => str_ireplace($name, '–', $e['k'])] + $e,
                json_decode((string)$spiel->ticker, true) ?: []);
            $spiel->updateAttributes(['ticker' => json_encode($ereignisse, JSON_UNESCAPED_UNICODE)]);
            $n++;
        }
        $this->stdout("„{$name}“ gesperrt, in $n Spielberichten entfernt (gesperrt: " . count($liste) . ")\n");
        return ExitCode::OK;
    }

    public function actionBfv($id, $bfvId)
    {
        $verein = Verein::findOne((int)$id);
        if ($verein === null || ($bfvId !== '-' && !preg_match('/^[0-9A-Z]{32}$/', $bfvId))) {
            $this->stderr("Verein nicht gefunden oder Kennung ungültig (32 Zeichen aus bfv.de/vereine/<name>/<kennung>)\n");
            return ExitCode::DATAERR;
        }
        if ($bfvId === '-') {
            foreach ($verein->mannschaften as $m) {
                $m->delete();
            }
            $verein->updateAttributes(['bfv_id' => null, 'bfv_name' => null]);
            $this->stdout("{$verein->name}: Verknüpfung mit dem BFV gelöst, Spiele gelöscht\n");
            return ExitCode::OK;
        }
        $verein->updateAttributes(['bfv_id' => $bfvId]);
        $this->stdout("{$verein->name}: mit dem BFV verknüpft, rufe Spiele ab …\n");
        return $this->actionSpiele();
    }
}
