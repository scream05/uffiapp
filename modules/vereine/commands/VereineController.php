<?php

namespace humhub\modules\vereine\commands;

use humhub\modules\vereine\components\Bfv;
use humhub\modules\vereine\models\Verein;
use Yii;
use yii\console\Controller;
use yii\console\ExitCode;

/**
 * Spiele der Fußballvereine vom BFV (Crontab stündlich, am Wochenende nachmittags halbstündlich):
 *   /app/yii vereine/spiele
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
