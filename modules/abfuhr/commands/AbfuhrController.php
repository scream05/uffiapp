<?php

namespace humhub\modules\abfuhr\commands;

use humhub\modules\abfuhr\components\Erinnerung;
use Yii;
use yii\console\Controller;
use yii\console\ExitCode;

/** Fällige Abfuhr-Erinnerungen verschicken: /app/yii abfuhr/erinnern (Crontab alle 15 Minuten); [1] = nur zählen */
class AbfuhrController extends Controller
{
    public function actionErinnern($dryRun = 0)
    {
        // Zwei Läufe gleichzeitig könnten dieselbe Erinnerung doppelt schicken
        if (!Yii::$app->mutex->acquire('abfuhr-erinnern', 0)) {
            return ExitCode::OK;
        }
        try {
            $n = Erinnerung::run(null, (bool)$dryRun);
        } finally {
            Yii::$app->mutex->release('abfuhr-erinnern');
        }
        if ($n || $dryRun) {
            $this->stdout(date('Y-m-d H:i') . ": $n Abfuhr-Erinnerung(en)" . ($dryRun ? ' fällig (Probelauf)' : ' verschickt') . "\n");
        }
        return ExitCode::OK;
    }
}
