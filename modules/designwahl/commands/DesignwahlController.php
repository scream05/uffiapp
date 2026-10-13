<?php

namespace humhub\modules\designwahl\commands;

use humhub\modules\designwahl\Module;
use Yii;
use yii\console\Controller;
use yii\console\ExitCode;

/** Standard-Design für Gäste und Konten ohne eigene Wahl */
class DesignwahlController extends Controller
{
    /** /app/yii designwahl/standard [klassisch|neu] – ohne Angabe: aktuellen Standard zeigen */
    public function actionStandard($design = null)
    {
        /** @var Module $module */
        $module = Yii::$app->getModule('designwahl');
        if ($design !== null) {
            if (!isset(Module::DESIGNS[$design])) {
                $this->stderr("Erlaubt: " . implode(', ', array_keys(Module::DESIGNS)) . "\n");
                return ExitCode::USAGE;
            }
            $module->settings->set('standard', $design);
        }
        $this->stdout("Standard: {$module->getStandard()}\n");
        return ExitCode::OK;
    }
}
