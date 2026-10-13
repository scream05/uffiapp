<?php

namespace humhub\modules\maengelmelder\controllers;

use humhub\components\access\ControllerAccess;
use humhub\components\Controller;
use humhub\modules\maengelmelder\components\Osm;
use Yii;
use yii\web\NotFoundHttpException;

/** Kartenkacheln über unseren Server: /maengelmelder/tile?z=…&x=…&y=… (nur Raum Uffenheim, Gäste mit Begrenzung pro IP) */
class TileController extends Controller
{
    protected function getAccessRules()
    {
        return []; // auch für Gäste (Karte), Begrenzung pro IP in actionIndex
    }

    /** Kacheln je IP und Stunde für Gäste (eine Karte braucht beim Zoomen/Schieben einige Dutzend) */
    private const GAST_LIMIT = 1500;

    public function actionIndex($z, $x, $y)
    {
        if (Yii::$app->user->isGuest) {
            $key = 'maengel-tile-' . Yii::$app->request->userIP . '-' . date('YmdH');
            $n = (int)Yii::$app->cache->get($key) + 1;
            Yii::$app->cache->set($key, $n, 3600);
            if ($n > self::GAST_LIMIT) {
                throw new \yii\web\TooManyRequestsHttpException();
            }
        }
        $png = Osm::tile((int)$z, (int)$x, (int)$y);
        if ($png === null) {
            throw new NotFoundHttpException();
        }
        $response = Yii::$app->response;
        $response->format = \yii\web\Response::FORMAT_RAW;
        $response->headers->set('Content-Type', 'image/png');
        $response->headers->set('Cache-Control', 'private, max-age=604800');
        return $png;
    }
}
