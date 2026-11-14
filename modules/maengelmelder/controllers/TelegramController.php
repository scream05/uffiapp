<?php

namespace humhub\modules\maengelmelder\controllers;

use humhub\components\access\ControllerAccess;
use humhub\components\Controller;
use humhub\modules\maengelmelder\components\Telegram;
use humhub\modules\maengelmelder\jobs\BauhofJob;
use humhub\modules\maengelmelder\models\BauhofNachricht;
use humhub\modules\maengelmelder\models\Mitarbeiter;
use Yii;
use yii\web\Response;

/**
 * Webhook der Telegram Bot API: https://community.rundumuffni.de/maengelmelder/telegram
 * Ohne Anmeldung erreichbar, abgesichert über das Secret-Token im Kopf (X-Telegram-Bot-Api-Secret-Token).
 * Verbinden per „/start <Code>“ (persönlicher Link), danach laufen Nachrichten/Knöpfe über Bauhof::verarbeiten.
 */
class TelegramController extends Controller
{
    public $access = ControllerAccess::class;
    public $enableCsrfValidation = false;
    protected $doNotInterceptActionIds = ['*'];

    protected function getAccessRules()
    {
        return [];
    }

    public function actionIndex()
    {
        Yii::$app->response->format = Response::FORMAT_RAW;
        $request = Yii::$app->request;
        $secret = $this->module->telegramZugang('webhook_secret');
        if (!$request->isPost || $secret === null
            || !hash_equals($secret, (string)$request->headers->get('X-Telegram-Bot-Api-Secret-Token'))) {
            Yii::$app->response->statusCode = 403;
            return 'Forbidden';
        }
        $body = $request->getRawBody();
        if (strlen($body) > 2 * 1024 * 1024) {
            return 'OK';
        }
        $update = json_decode($body, true);
        if (is_array($update)) {
            try {
                $this->verarbeiteUpdate($update);
            } catch (\Throwable $e) {
                Yii::error('Telegram-Webhook: ' . $e->getMessage(), 'maengelmelder');
            }
        }
        return 'OK'; // immer 200, sonst wiederholt Telegram
    }

    private function verarbeiteUpdate(array $update): void
    {
        if (isset($update['callback_query'])) {
            $this->callback($update['callback_query']);
            return;
        }
        $msg = $update['message'] ?? null;
        if (!is_array($msg) || !isset($msg['chat']['id'])) {
            return;
        }
        $chatId = (string)$msg['chat']['id'];
        $text = trim((string)($msg['text'] ?? $msg['caption'] ?? ''));

        // Verbinden: „/start <Code>“ vom persönlichen Link
        if (preg_match('/^\/start\s+(\S+)/', $text, $tr)) {
            $this->verbinden($chatId, $tr[1], $msg);
            return;
        }

        $m = Mitarbeiter::findOne(['telegram_chat_id' => $chatId, 'aktiv' => 1]);
        if ($m === null) {
            Telegram::sendeText($chatId, 'Diese Telegram-Verbindung gehört zu keinem aktiven Bauhof-Mitarbeiter. '
                . 'Bitte den persönlichen Verbinden-Link der Bauhofleitung verwenden.');
            return;
        }

        // Kanal noch nicht auf Telegram → nur bestätigen, dass die Verbindung steht (Aufträge laufen noch über WhatsApp)
        if ($this->module->bauhofKanal() !== 'telegram') {
            Telegram::sendeText($chatId, "Alles klar, {$m->name} – deine Telegram-Verbindung steht. "
                . 'Bis der Bauhof auf Telegram umstellt, bekommst du Aufträge noch wie gewohnt.');
            return;
        }

        [$typ, $mediaId] = $this->inhalt($msg);
        if ($typ === null) {
            Telegram::sendeText($chatId, 'Das kann ich leider nicht verarbeiten – bitte Text oder Foto schicken.');
            return;
        }
        $this->eintragen($m, $typ, $typ === 'text' || $typ === 'image' || $typ === 'document' ? $text : null, $mediaId,
            (string)$msg['message_id'], (int)($msg['date'] ?? time()));
    }

    private function callback(array $cb): void
    {
        $cbId = (string)($cb['id'] ?? '');
        if ($cbId !== '') {
            Telegram::callbackBeantworten($cbId);
        }
        $chatId = isset($cb['message']['chat']['id']) ? (string)$cb['message']['chat']['id'] : null;
        $daten = (string)($cb['data'] ?? '');
        if ($chatId === null || $daten === '' || $this->module->bauhofKanal() !== 'telegram') {
            return;
        }
        $m = Mitarbeiter::findOne(['telegram_chat_id' => $chatId, 'aktiv' => 1]);
        if ($m === null) {
            return;
        }
        $this->eintragen($m, 'knopf', $daten, null, 'c' . $cbId, time());
    }

    /** Neue eingegangene Nachricht speichern und zur Auswertung einreihen (Dedup je Mitarbeiter) */
    private function eintragen(Mitarbeiter $m, string $typ, ?string $text, ?string $mediaId, string $ref, int $zeit): void
    {
        if (BauhofNachricht::find()->where(['wamid' => $ref, 'mitarbeiter_id' => $m->id])->exists()) {
            return;
        }
        $n = new BauhofNachricht([
            'mitarbeiter_id' => $m->id,
            'richtung' => BauhofNachricht::EIN,
            'typ' => $typ,
            'text' => $text,
            'media_id' => $mediaId,
            'wamid' => mb_substr($ref, 0, 64),
            'created_at' => date('Y-m-d H:i:s', $zeit),
        ]);
        $n->save(false);
        $m->updateAttributes(['letzte_eingang_at' => date('Y-m-d H:i:s')]);
        Yii::$app->queue->push(new BauhofJob(['id' => $n->id]));
    }

    /** @return array{0:?string,1:?string} [Typ (text|image|document), file_id] */
    private function inhalt(array $msg): array
    {
        if (!empty($msg['photo']) && is_array($msg['photo'])) {
            $groesste = end($msg['photo']); // Telegram liefert aufsteigende Größen
            return ['image', $groesste['file_id'] ?? null];
        }
        if (!empty($msg['document']['file_id'])) {
            return ['document', $msg['document']['file_id']];
        }
        if (isset($msg['text'])) {
            return ['text', null];
        }
        return [null, null];
    }

    private function verbinden(string $chatId, string $token, array $msg): void
    {
        $m = Mitarbeiter::findOne(['telegram_link_token' => $token]);
        if ($m === null) {
            Telegram::sendeText($chatId, 'Dieser Verbinden-Link ist nicht (mehr) gültig. Bitte bei der Bauhofleitung einen neuen anfordern.');
            return;
        }
        // Dieselbe chat_id darf nicht an zwei Mitarbeiter hängen: eine evtl. frühere Bindung lösen
        Mitarbeiter::updateAll(['telegram_chat_id' => null], ['and', ['telegram_chat_id' => $chatId], ['not', ['id' => $m->id]]]);
        $m->updateAttributes(['telegram_chat_id' => $chatId, 'telegram_link_token' => null]);
        $hinweis = $this->module->bauhofKanal() === 'telegram'
            ? 'Ab jetzt bekommst du deine Aufträge hier über Telegram.'
            : 'Sobald der Bauhof auf Telegram umstellt, bekommst du deine Aufträge hier.';
        Telegram::sendeText($chatId, "Hallo {$m->name}! 👷 Deine Telegram-Verbindung mit dem Bauhof Uffenheim steht. $hinweis");
    }
}
