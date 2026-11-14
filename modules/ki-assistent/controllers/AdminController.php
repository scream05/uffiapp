<?php

namespace humhub\modules\kiAssistent\controllers;

use humhub\modules\admin\components\Controller;
use humhub\modules\kiAssistent\Module;
use Yii;

/**
 * Administration → KI-Anbieter (nur Admins, adminOnly der Basisklasse): wählt, welcher KI-Anbieter für Uffi,
 * Übersetzen, WhatsApp-Auslesen, Stadtrat und Mitteilungsblatt gilt. Die Schlüssel bleiben Docker-Secrets
 * auf dem Server und werden hier weder angezeigt noch eingegeben.
 *
 * @property Module $module
 */
class AdminController extends Controller
{
    public function actionIndex()
    {
        $m = $this->module;
        $request = Yii::$app->request;
        if ($request->isPost) {
            $anbieter = (string)$request->post('anbieter');
            $region = (string)$request->post('bedrock_region');
            $modell = trim((string)$request->post('bedrock_modell'));
            if (!isset(Module::ANBIETER[$anbieter])) {
                $this->view->error(Yii::t('KiAssistentModule.base', 'Unbekannter Anbieter.'));
            } elseif ($m->getApiKey($anbieter) === null) {
                $this->view->error(Yii::t('KiAssistentModule.base', 'Für {anbieter} ist auf dem Server kein Schlüssel hinterlegt ({datei}).',
                    ['anbieter' => Module::ANBIETER[$anbieter], 'datei' => $m->getApiKeyDatei($anbieter)]));
            } elseif ($modell !== '' && !preg_match('/^[a-z0-9.:\-]{3,80}$/', $modell)) {
                $this->view->error(Yii::t('KiAssistentModule.base', 'Ungültiger Modellname.'));
            } else {
                $vorher = $m->getAnbieter();
                $alt = ['bedrock-region' => $m->settings->get('bedrock-region'), 'bedrock-modell' => $m->settings->get('bedrock-modell')];
                if (isset(Module::BEDROCK_REGIONEN[$region])) {
                    $m->settings->set('bedrock-region', $region);
                }
                $m->settings->set('bedrock-modell', $modell);
                // Erst prüfen, ob der Anbieter mit diesem Modell antwortet – sonst bleibt alles beim Alten (Uffi fiele sonst aus)
                if (($fehler = $this->pruefen($anbieter)) !== null) {
                    foreach ($alt as $name => $wert) {
                        $wert === null ? $m->settings->delete($name) : $m->settings->set($name, $wert);
                    }
                    $this->view->error(Yii::t('KiAssistentModule.base', 'Nicht gespeichert, {anbieter} antwortet nicht mit Modell {modell}: {meldung}',
                        ['anbieter' => Module::ANBIETER[$anbieter], 'modell' => $m->getModel('standard', null, $anbieter), 'meldung' => $fehler]));
                    return $this->render('index', ['modul' => $m]);
                }
                $m->settings->set('anbieter', $anbieter);
                Yii::info('KI-Anbieter: ' . $vorher . ' → ' . $anbieter . ' (Bedrock ' . $m->getBedrockRegion() . ', '
                    . ($modell ?: 'Standardmodell') . '), geändert von Konto ' . Yii::$app->user->id, 'ki-assistent');
                $this->view->saved();
                return $this->redirect(['index']);
            }
        }
        return $this->render('index', ['modul' => $m]);
    }

    /** Kurze Testanfrage an einen Anbieter (auch an einen, der gerade nicht aktiv ist); Antwort als JSON */
    public function actionTesten()
    {
        $this->forcePostRequest();
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        $anbieter = (string)Yii::$app->request->post('anbieter');
        if (!isset(Module::ANBIETER[$anbieter])) {
            return ['ok' => false, 'meldung' => Yii::t('KiAssistentModule.base', 'Unbekannter Anbieter.')];
        }
        $start = microtime(true);
        $text = null;
        if (($fehler = $this->pruefen($anbieter, $text)) !== null) {
            return ['ok' => false, 'meldung' => Yii::t('KiAssistentModule.base', 'Fehler: {meldung}', ['meldung' => $fehler])];
        }
        return ['ok' => true, 'meldung' => Yii::t('KiAssistentModule.base', 'Verbindung klappt: „{antwort}“ ({sekunden} s, Modell {modell})', [
            'antwort' => mb_substr(trim($text), 0, 60), 'sekunden' => number_format(microtime(true) - $start, 1, ',', ''),
            'modell' => $this->module->getModel('standard', null, $anbieter)])];
    }

    /** Kurze Testanfrage mit den gespeicherten Einstellungen; null bei Erfolg, sonst der Kern der Fehlermeldung */
    private function pruefen(string $anbieter, ?string &$text = null): ?string
    {
        $m = $this->module;
        try {
            [$text, $in, $out] = $m->ki('standard', null, 60, $anbieter)
                ->ask('Antworte mit genau einem Wort auf Deutsch.', [['role' => 'user', 'text' => 'Wie heißt die Hauptstadt Bayerns?']]);
            $m->addTestUsage($in, $out);
            return null;
        } catch (\Throwable $e) {
            Yii::warning('KI-Verbindungstest ' . $anbieter . ': ' . $e->getMessage(), 'ki-assistent');
            // Kern der Fehlermeldung ohne Stacktrace/JSON-Hülle
            return preg_match('/"message":\s*"([^"]+)"/', $e->getMessage(), $x) ? stripslashes($x[1]) : mb_substr($e->getMessage(), 0, 300);
        }
    }
}
