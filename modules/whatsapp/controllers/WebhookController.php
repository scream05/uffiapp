<?php

namespace humhub\modules\whatsapp\controllers;

use humhub\components\access\ControllerAccess;
use humhub\components\Controller;
use humhub\modules\whatsapp\components\Api;
use humhub\modules\whatsapp\components\Verarbeitung;
use Yii;
use yii\web\Response;

/**
 * Webhook der WhatsApp Cloud API: https://community.rundumuffni.de/whatsapp/webhook
 * GET = Bestätigung bei der Einrichtung (Verify-Token), POST = eingehende Nachrichten (signiert mit dem App-Geheimcode).
 * Ohne Anmeldung erreichbar; antwortet schnell, die Auswertung läuft im Hintergrund.
 */
class WebhookController extends Controller
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
        Yii::$app->response->headers->set('Content-Type', 'text/plain; charset=utf-8');
        $request = Yii::$app->request;

        if ($request->isGet) {
            // PHP macht aus hub.mode usw. hub_mode
            $token = $this->module->zugang('verify_token');
            if ($token !== null && $request->get('hub_mode') === 'subscribe'
                && hash_equals($token, (string)$request->get('hub_verify_token'))) {
                return (string)$request->get('hub_challenge');
            }
            Yii::$app->response->statusCode = 403;
            return 'Forbidden';
        }

        if (!$request->isPost) {
            Yii::$app->response->statusCode = 405;
            return '';
        }
        $body = $request->getRawBody();
        if (strlen($body) > 1024 * 1024 || !Api::signaturOk($body, $request->headers->get('X-Hub-Signature-256'))) {
            Yii::warning('Webhook mit ungültiger Signatur abgewiesen', 'whatsapp');
            Yii::$app->response->statusCode = 401;
            return 'Invalid signature';
        }
        $payload = json_decode($body, true);
        if (is_array($payload) && ($payload['object'] ?? '') === 'whatsapp_business_account') {
            try {
                Verarbeitung::empfangen($payload);
            } catch (\Throwable $e) {
                // 500 → Meta wiederholt den Aufruf später
                Yii::error('Webhook: ' . $e->getMessage(), 'whatsapp');
                Yii::$app->response->statusCode = 500;
                return 'Error';
            }
        }
        return 'OK';
    }
}
