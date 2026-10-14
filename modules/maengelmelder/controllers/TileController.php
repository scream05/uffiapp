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

    /** Kacheln je Stunde für Gäste (je Anschluss, IPv6 je /64) bzw. je Konto (eine Karte braucht beim Zoomen/Schieben einige Dutzend) */
    private const GAST_LIMIT = 1500;
    private const KONTO_LIMIT = 3000;

    public function actionIndex($z, $x, $y)
    {
        $gast = Yii::$app->user->isGuest;
        $wer = $gast ? self::anschluss() : 'u' . Yii::$app->user->id;
        $key = 'maengel-tile-' . $wer . '-' . date('YmdH');
        $n = (int)Yii::$app->cache->get($key) + 1;
        Yii::$app->cache->set($key, $n, 3600);
        if ($n > ($gast ? self::GAST_LIMIT : self::KONTO_LIMIT)) {
            throw new \yii\web\TooManyRequestsHttpException();
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

    /** IP-Adresse, bei IPv6 auf das /64-Netz gekürzt (ein Anschluss hat dort beliebig viele Adressen) */
    private static function anschluss(): string
    {
        $ip = (string)Yii::$app->request->userIP;
        if (str_contains($ip, ':') && ($bin = @inet_pton($ip)) !== false) {
            return bin2hex(substr($bin, 0, 8)) . '::/64';
        }
        return $ip;
    }
}
