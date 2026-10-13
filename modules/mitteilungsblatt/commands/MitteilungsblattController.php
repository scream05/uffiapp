<?php

namespace humhub\modules\mitteilungsblatt\commands;

use humhub\modules\mitteilungsblatt\components\Abruf;
use humhub\modules\mitteilungsblatt\models\Ausgabe;
use Yii;
use yii\console\Controller;
use yii\console\ExitCode;

/**
 * Crontab: /app/yii mitteilungsblatt/abrufen  (neue Ausgaben von uffenheim.de, eine Logzeile je Lauf)
 */
class MitteilungsblattController extends Controller
{
    /** @param int $probelauf 1 = nur anzeigen, was auf uffenheim.de steht */
    public function actionAbrufen($probelauf = 0)
    {
        $module = Yii::$app->getModule('mitteilungsblatt');
        try {
            [$neu, $aktualisiert, $geloescht] = (new Abruf($module, fn($s) => $this->stdout($s)))->holen((bool)$probelauf);
        } catch (\Throwable $e) {
            $this->stderr(date('Y-m-d H:i') . ' Mitteilungsblatt: ' . $e->getMessage() . "\n");
            return ExitCode::UNSPECIFIED_ERROR;
        }
        if (!$probelauf) {
            $this->stdout(date('Y-m-d H:i') . " Mitteilungsblatt: $neu neu, $aktualisiert aktualisiert, $geloescht gelöscht, "
                . Ausgabe::find()->count() . " im Archiv\n");
        }
        return ExitCode::OK;
    }

    /**
     * Ältere Ausgaben aus einem Ordner im Container übernehmen (ohne Beiträge im Stream), z. B. aus der Wayback Machine.
     * Optional quellen.json im Ordner: {"datei.pdf": "Herkunfts-URL"} für den Link „Original“.
     * @param string $ordner Ordner mit PDF-Dateien
     * @param int $probelauf 1 = nur erkennen, nichts speichern
     */
    public function actionEinlesen($ordner, $probelauf = 0)
    {
        $quellen = json_decode((string)@file_get_contents("$ordner/quellen.json"), true) ?: [];
        $abruf = new Abruf(Yii::$app->getModule('mitteilungsblatt'), fn($s) => $this->stdout($s));
        $dateien = glob("$ordner/*.pdf");
        foreach ($dateien as $datei) {
            try {
                $zeile = $abruf->einlesen($datei, $quellen[basename($datei)] ?? '', (bool)$probelauf);
            } catch (\Throwable $e) {
                $zeile = '! ' . basename($datei) . ': ' . $e->getMessage();
            }
            $this->stdout($zeile . "\n");
        }
        $this->stdout(count($dateien) . ' Dateien, ' . Ausgabe::find()->count() . " Ausgaben im Archiv\n");
        return ExitCode::OK;
    }

    /**
     * Für alle Mitglieder freigeben (1) oder nur für Admins (0). Ohne Angabe: Stand anzeigen.
     * Mit Freigabe bekommen neue Ausgaben einen Beitrag im Space; die neueste vorhandene mit „beitrag“ nachholen.
     */
    public function actionFreigeben($an = null)
    {
        $module = Yii::$app->getModule('mitteilungsblatt');
        if ($an !== null) {
            $module->settings->set('freigegeben', $an ? 1 : 0);
        }
        $this->stdout('Mitteilungsblatt ' . ($module->istFreigegeben() ? 'für alle Mitglieder freigegeben' : 'nur für Admins') . "\n");
        return ExitCode::OK;
    }

    /** Beitrag im Space zur neuesten Ausgabe (falls es noch keinen gibt) */
    public function actionBeitrag()
    {
        $a = Ausgabe::find()->orderBy(['jahr' => SORT_DESC, 'nr' => SORT_DESC])->one();
        if ($a === null || $a->post_id) {
            $this->stdout($a ? "Beitrag zu {$a->titel} gibt es schon\n" : "Keine Ausgabe im Archiv\n");
            return ExitCode::OK;
        }
        (new Abruf(Yii::$app->getModule('mitteilungsblatt'), fn($s) => $this->stdout($s)))->beitrag($a);
        $this->stdout($a->post_id ? "Beitrag zu {$a->titel} angelegt\n" : '');
        return ExitCode::OK;
    }
}
