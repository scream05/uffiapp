<?php

namespace humhub\modules\whatsapp\commands;

use humhub\modules\whatsapp\components\Api;
use humhub\modules\whatsapp\components\Auslesen;
use humhub\modules\whatsapp\components\Verarbeitung;
use humhub\modules\whatsapp\models\Entwurf;
use humhub\modules\whatsapp\models\Nachricht;
use Yii;
use yii\console\Controller;
use yii\console\ExitCode;

/**
 * /app/yii whatsapp/verarbeiten        Einreichungen auswerten, Altes löschen (Crontab als Absicherung zum Hintergrundjob)
 * /app/yii whatsapp/pruefen            Zugangsdaten und Nummer bei Meta prüfen
 * /app/yii whatsapp/abonnieren         App für das WhatsApp-Konto (waba_id) auf Webhooks abonnieren
 * /app/yii whatsapp/auslesen <Datei> [Text]   Testlauf: Plakat auslesen, ohne etwas zu speichern
 */
class WhatsappController extends Controller
{
    public function actionVerarbeiten()
    {
        Verarbeitung::lauf(fn($s) => $this->stdout(date('Y-m-d H:i') . " $s\n"));
        return ExitCode::OK;
    }

    public function actionPruefen()
    {
        $module = Yii::$app->getModule('whatsapp');
        foreach (['token', 'app_secret', 'phone_number_id', 'waba_id', 'verify_token'] as $key) {
            $this->stdout(str_pad($key, 16) . ($module->zugang($key) !== null ? "gesetzt\n" : "FEHLT\n"));
        }
        try {
            $info = Api::get($module->zugang('phone_number_id') . '?fields=display_phone_number,verified_name,name_status,quality_rating,code_verification_status');
            foreach ($info as $k => $v) {
                $this->stdout(str_pad($k, 26) . (is_scalar($v) ? $v : json_encode($v)) . "\n");
            }
            if ($waba = $module->zugang('waba_id')) {
                $apps = Api::get("$waba/subscribed_apps");
                $this->stdout('Webhook-Abo                ' . (empty($apps['data']) ? 'NEIN – whatsapp/abonnieren ausführen' : 'ja') . "\n");
            }
        } catch (\Throwable $e) {
            $this->stderr($e->getMessage() . "\n");
            return ExitCode::UNSPECIFIED_ERROR;
        }
        [$n, $in, $out] = $module->getUsage();
        $this->stdout("Auswertungen heute: $n (Tokens $in/$out), offene Entwürfe: "
            . Entwurf::find()->where(['status' => Entwurf::STATUS_OFFEN])->count() . ', unverarbeitete Nachrichten: '
            . Nachricht::find()->where(['status' => Nachricht::STATUS_NEU])->count() . "\n");
        return ExitCode::OK;
    }

    public function actionAbonnieren()
    {
        $waba = Yii::$app->getModule('whatsapp')->zugang('waba_id');
        if ($waba === null) {
            $this->stderr("waba_id fehlt in secrets/whatsapp.json\n");
            return ExitCode::CONFIG;
        }
        try {
            $this->stdout(json_encode(Api::post("$waba/subscribed_apps")) . "\n");
        } catch (\Throwable $e) {
            $this->stderr($e->getMessage() . "\n");
            return ExitCode::UNSPECIFIED_ERROR;
        }
        return ExitCode::OK;
    }

    public function actionAuslesen($datei, $text = '')
    {
        $space = Yii::$app->getModule('whatsapp')->getCalendarSpace();
        $kategorien = $space ? array_map(fn($t) => $t->name, Entwurf::typen($space)) : [];
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($datei);
        [$termine, $kein, $in, $out] = Auslesen::termine($text, [[$datei, $mime]], $kategorien);
        $this->stdout(json_encode(['termine' => $termine, 'kein_termin' => $kein, 'tokens' => [$in, $out]],
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n");
        return ExitCode::OK;
    }
}
