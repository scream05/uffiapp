<?php

namespace humhub\modules\wochenblick\commands;

use humhub\modules\user\models\User;
use humhub\modules\wochenblick\components\Ueberblick;
use Yii;
use yii\console\Controller;
use yii\console\ExitCode;

/**
 * /app/yii wochenblick/senden [1 = nur zählen]   fällige Überblicke (Crontab alle 15 Minuten, verschickt nur freitags ab 17 Uhr)
 * /app/yii wochenblick/zeigen <benutzer>          Überblick als Text ausgeben, ohne zu senden
 * /app/yii wochenblick/test <benutzer> [mail=1]   sofort an dieses Konto schicken (auch ohne Einschalten)
 * /app/yii wochenblick/selbsttest                 Versandregeln prüfen (Probelauf in einer Transaktion, wird zurückgerollt)
 */
class WochenblickController extends Controller
{
    public function actionSenden($dryRun = 0)
    {
        // Zwei Läufe gleichzeitig könnten denselben Überblick doppelt schicken
        if (!Yii::$app->mutex->acquire('wochenblick-senden', 0)) {
            return ExitCode::OK;
        }
        try {
            $n = Ueberblick::run(null, (bool)$dryRun);
        } finally {
            Yii::$app->mutex->release('wochenblick-senden');
        }
        if ($n || $dryRun) {
            $this->stdout(date('Y-m-d H:i') . ": $n Wochenüberblick(e)" . ($dryRun ? ' fällig (Probelauf)' : ' verschickt') . "\n");
        }
        return ExitCode::OK;
    }

    public function actionSelbsttest()
    {
        $m = Yii::$app->getModule('wochenblick');
        $frei = User::findOne(['username' => $m->freigabe[0] ?? 'admin']);
        $andere = User::find()->where(['status' => User::STATUS_ENABLED])->andWhere(['not', ['id' => $frei->id]])->one();
        $freitag = strtotime('next friday ' . $m->stunde . ':30');
        $fehler = 0;
        $pruefen = function (string $name, int $soll, int $ist) use (&$fehler) {
            $this->stdout(($soll === $ist ? 'ok   ' : 'FEHL ') . "$name (soll $soll, ist $ist)\n");
            $fehler += $soll !== $ist;
        };
        $tx = Yii::$app->db->beginTransaction();
        try {
            $s = $m->settings->user($frei);
            $s->set('aktiv', 0);
            $s->delete('gesendet');
            $pruefen('ausgeschaltet: nichts', 0, Ueberblick::run($freitag, true));
            $s->set('aktiv', 1);
            $pruefen('Freitag ' . $m->stunde . ':30, eingeschaltet', 1, Ueberblick::run($freitag, true));
            $pruefen('Freitag vor ' . $m->stunde . ' Uhr', 0, Ueberblick::run($freitag - 3600, true));
            $pruefen('Freitag 3 Stunden später', 0, Ueberblick::run($freitag + 3 * 3600, true));
            $pruefen('Donnerstag', 0, Ueberblick::run($freitag - 86400, true));
            $s->set('gesendet', date('Y-m-d', $freitag));
            $pruefen('schon gesendet', 0, Ueberblick::run($freitag, true));
            if ($andere !== null && $m->freigabe !== null) {
                $s->delete('gesendet');
                $m->settings->user($andere)->set('aktiv', 1);
                $pruefen('nicht freigegebenes Konto eingeschaltet', 1, Ueberblick::run($freitag, true));
            }
            $d = Ueberblick::daten($frei, $freitag);
            $pruefen('Zeitraum beginnt Samstag', 6, (int)date('w', strtotime($d['von'])));
            $pruefen('Zeitraum endet Freitag', 5, (int)date('w', strtotime($d['bis'])));
        } finally {
            $tx->rollBack();
            Yii::$app->cache->flush();
        }
        $this->stdout($fehler ? "$fehler Fehler\n" : "alles ok (zurückgerollt)\n");
        return $fehler ? ExitCode::UNSPECIFIED_ERROR : ExitCode::OK;
    }

    public function actionZeigen($username)
    {
        $user = User::findOne(['username' => $username]);
        if ($user === null) {
            $this->stderr("Konto $username fehlt\n");
            return ExitCode::DATAERR;
        }
        Yii::$app->i18n->setUserLocale($user);
        $d = Ueberblick::daten($user);
        $this->stdout(Ueberblick::kurz($d) . "\n\n" . Yii::$app->view->renderFile('@wochenblick/views/mail/text.php', ['d' => $d]) . "\n");
        return ExitCode::OK;
    }

    public function actionTest($username, $mail = 1)
    {
        $user = User::findOne(['username' => $username]);
        if ($user === null) {
            $this->stderr("Konto $username fehlt\n");
            return ExitCode::DATAERR;
        }
        $ok = Ueberblick::senden($user, null, (bool)$mail);
        $this->stdout("Wochenüberblick an {$user->username} geschickt (Glocke/Push" . ($mail ? ', Mail ' . ($ok ? 'ok' : 'FEHLER') : '') . ")\n");
        return $ok ? ExitCode::OK : ExitCode::UNSPECIFIED_ERROR;
    }
}
