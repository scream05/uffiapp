<?php

namespace humhub\modules\designwahl\controllers;

use DateTime;
use humhub\components\Controller;
use humhub\modules\calendar\helpers\CalendarUtils;
use humhub\modules\calendar\interfaces\CalendarService;
use humhub\modules\space\models\Space;
use Yii;
use yii\web\NotFoundHttpException;

/**
 * Öffentliches Kalender-Abo (ohne Konto): /designwahl/abo?kalender=veranstaltungen liefert alle Termine des Space,
 * die auch Gäste sehen dürfen, als iCal-Datei. Kalender-Apps rufen die Adresse regelmäßig ab.
 * Abfuhrtermine kommen als fertige Dateien aus scripts/abfall-kalender.py (/abfall/abfall-kernstadt.ics usw.).
 */
class AboController extends Controller
{
    /** Kalender-Schlüssel => Space-URL */
    public const KALENDER = ['veranstaltungen' => 'veranstaltungen'];

    protected function getAccessRules()
    {
        return []; // auch für Gäste
    }

    public function actionIndex($kalender = 'veranstaltungen')
    {
        if (!isset(self::KALENDER[$kalender])) {
            throw new NotFoundHttpException();
        }
        // Immer mit Gast-Rechten, auch wenn ein angemeldeter Nutzer die Adresse aufruft: alle Abonnenten bekommen dasselbe
        $ics = Yii::$app->cache->getOrSet('designwahl-abo-' . $kalender, function () use ($kalender) {
            $space = Space::findOne(['url' => self::KALENDER[$kalender]]);
            if ($space === null) {
                return false;
            }
            $user = Yii::$app->user;
            $identity = $user->getIdentity(false);
            $enableSession = $user->enableSession;
            $user->enableSession = false;
            $user->setIdentity(null);
            try {
                $termine = Yii::$app->getModule('calendar')->get(CalendarService::class)->getCalendarItems(
                    new DateTime('-30 days'), new DateTime('+1 year'), [], $space, null, true,
                );
                $ics = CalendarUtils::generateIcal($termine, 'Veranstaltungen in Uffenheim');
            } finally {
                $user->setIdentity($identity);
                $user->enableSession = $enableSession;
            }
            return self::ohnePersonen((string)$ics);
        }, 900);
        if ($ics === false || $ics === '') {
            Yii::$app->cache->delete('designwahl-abo-' . $kalender);
            throw new NotFoundHttpException();
        }
        return Yii::$app->response->sendContentAsFile($ics, $kalender . '.ics', [
            'mimeType' => 'text/calendar',
            'inline' => true,
        ]);
    }

    /** Veranstalter-/Teilnehmerzeilen (Namen von Konten) aus öffentlichen Kalenderdateien entfernen */
    public static function ohnePersonen(string $ics): string
    {
        // samt Folgezeilen (gefaltete Zeilen beginnen mit Leerzeichen/Tab)
        return preg_replace('/^(ORGANIZER|ATTENDEE)[;:].*\r?\n([ \t].*\r?\n)*/m', '', $ics);
    }
}
