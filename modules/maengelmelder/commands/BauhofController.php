<?php

namespace humhub\modules\maengelmelder\commands;

use humhub\modules\content\models\Content;
use humhub\modules\file\models\File;
use humhub\modules\maengelmelder\models\BauhofNachricht;
use humhub\modules\maengelmelder\models\MaengelStatus;
use humhub\modules\maengelmelder\Module;
use humhub\modules\whatsapp\components\Api;
use Yii;
use yii\console\Controller;
use yii\console\ExitCode;

/**
 * Bauhof (Mängelmelder per WhatsApp):
 *   /app/yii bauhof/vorlagen            Status der WhatsApp-Vorlagen bei Meta
 *   /app/yii bauhof/vorlagen 1          fehlende Vorlagen zur Prüfung einreichen
 *   /app/yii bauhof/aufraeumen          Verlauf/Fotos lange abgeschlossener Meldungen löschen (täglich per Crontab)
 */
class BauhofController extends Controller
{
    private function module(): Module
    {
        return Yii::$app->getModule('maengelmelder');
    }

    /** Vorlagen für den Erstkontakt (Kategorie „Utility“, Deutsch) */
    private function definitionen(): array
    {
        $mod = $this->module();
        return [
            $mod->vorlagen['auftrag'] => [
                'name' => $mod->vorlagen['auftrag'],
                'language' => $mod->vorlagenSprache,
                'category' => 'UTILITY',
                'components' => [
                    ['type' => 'BODY',
                        'text' => "Neuer Auftrag vom Bauhof Uffenheim: Nr. {{1}} – {{2}}, Ort: {{3}}.\n\nTippe auf „Auftrag anzeigen“, um Beschreibung, Fotos und Karte zu bekommen.",
                        'example' => ['body_text' => [['12', 'Straße / Gehweg', 'Marktplatz 1, Uffenheim']]]],
                    ['type' => 'BUTTONS', 'buttons' => [['type' => 'QUICK_REPLY', 'text' => 'Auftrag anzeigen']]],
                ],
            ],
            $mod->vorlagen['nachricht'] => [
                'name' => $mod->vorlagen['nachricht'],
                'language' => $mod->vorlagenSprache,
                'category' => 'UTILITY',
                'components' => [
                    ['type' => 'BODY',
                        'text' => "Du hast eine neue Nachricht vom Bauhof Uffenheim (von {{1}}).\n\nTippe auf „Nachricht lesen“, um sie anzuzeigen.",
                        'example' => ['body_text' => [['Bauhofleitung']]]],
                    ['type' => 'BUTTONS', 'buttons' => [['type' => 'QUICK_REPLY', 'text' => 'Nachricht lesen']]],
                ],
            ],
        ];
    }

    public function actionVorlagen($anlegen = 0)
    {
        $vorhanden = [];
        foreach (Api::vorlagen() as $v) {
            $vorhanden[$v['name']] = $v;
        }
        foreach ($this->definitionen() as $name => $def) {
            if (isset($vorhanden[$name])) {
                $v = $vorhanden[$name];
                $this->stdout("$name ({$v['language']}): {$v['status']}" . (!empty($v['rejected_reason']) && $v['rejected_reason'] !== 'NONE' ? " – {$v['rejected_reason']}" : '') . "\n");
            } elseif ($anlegen) {
                $res = Api::vorlageAnlegen($def);
                $this->stdout("$name: eingereicht, Status " . ($res['status'] ?? '?') . "\n");
            } else {
                $this->stdout("$name: fehlt (mit „bauhof/vorlagen 1“ einreichen)\n");
            }
        }
        return ExitCode::OK;
    }

    /**
     * Verlauf und WhatsApp-Fotos von Meldungen löschen, die seit $bauhofAufbewahrungTage abgeschlossen sind,
     * dazu Nachrichten ohne Meldung nach derselben Frist und verwaiste Dateien.
     */
    public function actionAufraeumen()
    {
        $frist = date('Y-m-d H:i:s', time() - $this->module()->bauhofAufbewahrungTage * 86400);
        $alt = MaengelStatus::find()->select('content_id')->where(['status' => MaengelStatus::CLOSED])
            ->andWhere(['<', 'updated_at', $frist])->column();
        $n = 0;
        foreach (BauhofNachricht::find()->where(['content_id' => $alt])
                     ->orWhere(['and', ['content_id' => null], ['<', 'created_at', $frist]])->each() as $msg) {
            $msg->delete();
            $n++;
        }
        // Dateien, deren Nachricht schon weg ist (z. B. Meldung gelöscht)
        $f = 0;
        foreach (File::find()->where(['object_model' => BauhofNachricht::class])
                     ->andWhere(['not exists', BauhofNachricht::find()->where('bauhof_nachricht.id = file.object_id')])->each() as $file) {
            $file->delete();
            $f++;
        }
        if ($n || $f) {
            $this->stdout(date('Y-m-d H:i:s') . ": $n Nachrichten, $f verwaiste Dateien gelöscht\n");
        }
        return ExitCode::OK;
    }
}
