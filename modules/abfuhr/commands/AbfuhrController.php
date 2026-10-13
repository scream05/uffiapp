<?php

namespace humhub\modules\abfuhr\commands;

use humhub\modules\abfuhr\components\Erinnerung;
use yii\console\Controller;
use yii\console\ExitCode;

/** Fällige Abfuhr-Erinnerungen verschicken: /app/yii abfuhr/erinnern (Crontab alle 15 Minuten); [1] = nur zählen */
class AbfuhrController extends Controller
{
    public function actionErinnern($dryRun = 0)
    {
        $n = Erinnerung::run(null, (bool)$dryRun);
        if ($n || $dryRun) {
            $this->stdout(date('Y-m-d H:i') . ": $n Abfuhr-Erinnerung(en)" . ($dryRun ? ' fällig (Probelauf)' : ' verschickt') . "\n");
        }
        return ExitCode::OK;
    }
}
