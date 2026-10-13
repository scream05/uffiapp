<?php

namespace humhub\modules\notdienst\commands;

use humhub\modules\notdienst\components\Notdienst;
use Yii;
use yii\console\Controller;
use yii\console\ExitCode;

/**
 * Crontab: /app/yii notdienst/abrufen  (holt den Apotheken-Notdienst der BLAK, eine Logzeile je Lauf)
 */
class NotdienstController extends Controller
{
    public function actionAbrufen()
    {
        $url = Yii::$app->getModule('notdienst')->getFeedUrl();
        if ($url === null) {
            $this->stderr(date('Y-m-d H:i') . " Kein Link in secrets/blak_notdienst_url\n");
            return ExitCode::CONFIG;
        }
        try {
            $n = Notdienst::fetch($url);
        } catch (\Throwable $e) {
            // Link enthält unsere Registrierung: nur die Fehlermeldung ausgeben
            $this->stderr(date('Y-m-d H:i') . ' Notdienst-Abruf fehlgeschlagen: ' . $e->getMessage() . "\n");
            return ExitCode::UNSPECIFIED_ERROR;
        }
        $this->stdout(date('Y-m-d H:i') . " Notdienst: $n Einträge\n");
        return ExitCode::OK;
    }
}
