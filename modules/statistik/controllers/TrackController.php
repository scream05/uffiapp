<?php

namespace humhub\modules\statistik\controllers;

use humhub\components\access\ControllerAccess;
use humhub\components\Controller;
use humhub\modules\statistik\components\Erfassung;
use Yii;
use yii\web\Response;

/**
 * Leichter Beacon-Endpunkt: /statistik/track/start wird vom Client gemeldet, wenn die Web-App als eigenständige
 * App (vom Startbildschirm) gestartet wurde. Markiert den laufenden Besuch als Start – ohne das Manifest zu
 * verändern (das würde die HumHub-App-Anmeldung brechen). Antwort ist bewusst kein HTML, daher zählt der
 * Aufruf selbst nicht als Seitenaufruf.
 */
class TrackController extends Controller
{
    public $access = ControllerAccess::class;
    public $enableCsrfValidation = false;
    protected $doNotInterceptActionIds = ['*'];

    protected function getAccessRules()
    {
        return []; // auch für Gäste (PWA ohne Anmeldung)
    }

    public function actionStart()
    {
        if (Yii::$app->request->isPost || Yii::$app->request->isGet) {
            try {
                Erfassung::markiereStart();
            } catch (\Throwable $e) {
                Yii::error($e, 'statistik');
            }
        }
        Yii::$app->response->format = Response::FORMAT_RAW;
        Yii::$app->response->statusCode = 204;
        return '';
    }
}
