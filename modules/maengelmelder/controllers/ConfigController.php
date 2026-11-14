<?php

namespace humhub\modules\maengelmelder\controllers;

use humhub\modules\admin\components\Controller;
use humhub\modules\maengelmelder\components\Telegram;
use humhub\modules\maengelmelder\Module;
use Yii;
use yii\helpers\Url;

/**
 * Administration → Mängelmelder (nur Admins): Kanal für die Bauhof-Mitarbeiter (WhatsApp oder Telegram) umschalten
 * und den Telegram-Webhook einrichten. Der Bot-Token bleibt Docker-Secret auf dem Server (secrets/telegram.json).
 *
 * @property Module $module
 */
class ConfigController extends Controller
{
    public function actionIndex()
    {
        $m = $this->module;
        $request = Yii::$app->request;

        if ($request->isPost && $request->post('aktion') === 'kanal') {
            $kanal = $request->post('bauhofKanal') === 'telegram' ? 'telegram' : 'whatsapp';
            if ($kanal === 'telegram' && !$m->telegramAktiv()) {
                $this->view->error('Telegram ist noch nicht eingerichtet: Es fehlt der Bot-Token (secrets/telegram.json).');
            } else {
                $m->settings->set('bauhofKanal', $kanal);
                $this->view->success('Kanal gespeichert: Bauhof-Mitarbeiter über ' . ($kanal === 'telegram' ? 'Telegram' : 'WhatsApp') . '.');
            }
            return $this->redirect(['index']);
        }

        if ($request->isPost && $request->post('aktion') === 'webhook-an') {
            try {
                $secret = $m->telegramZugang('webhook_secret') ?? throw new \RuntimeException('webhook_secret fehlt in secrets/telegram.json');
                $url = Url::to(['/maengelmelder/telegram/index'], true);
                Telegram::setzeWebhook($url, $secret);
                $this->view->success('Telegram-Webhook eingerichtet: ' . $url);
            } catch (\Throwable $e) {
                $this->view->error('Webhook fehlgeschlagen: ' . $e->getMessage());
            }
            return $this->redirect(['index']);
        }

        if ($request->isPost && $request->post('aktion') === 'webhook-aus') {
            try {
                Telegram::loescheWebhook();
                $this->view->success('Telegram-Webhook entfernt.');
            } catch (\Throwable $e) {
                $this->view->error('Konnte Webhook nicht entfernen: ' . $e->getMessage());
            }
            return $this->redirect(['index']);
        }

        // Telegram-Status (nur wenn Token da ist, sonst keine API-Aufrufe)
        $botName = null;
        $webhook = null;
        $fehler = null;
        if ($m->telegramAktiv()) {
            try {
                $botName = Telegram::botName();
                $webhook = Telegram::webhookInfo();
            } catch (\Throwable $e) {
                $fehler = $e->getMessage();
            }
        }

        return $this->render('index', [
            'kanal' => $m->bauhofKanal(),
            'telegramAktiv' => $m->telegramAktiv(),
            'botName' => $botName,
            'webhook' => $webhook,
            'erwarteteUrl' => Url::to(['/maengelmelder/telegram/index'], true),
            'fehler' => $fehler,
        ]);
    }
}
