<?php

namespace humhub\modules\eilmeldung\commands;

use humhub\modules\eilmeldung\components\Nina;
use humhub\modules\eilmeldung\components\Versand;
use humhub\modules\eilmeldung\models\Eilmeldung;
use humhub\modules\eilmeldung\notifications\EilmeldungNeu;
use humhub\modules\user\models\User;
use Yii;
use yii\console\Controller;
use yii\console\ExitCode;

/**
 * /app/yii eilmeldung/nina [1 = Probelauf]   NINA-Warnungen abgleichen (Crontab alle 5 Minuten)
 * /app/yii eilmeldung/liste                  laufende Eilmeldungen
 * /app/yii eilmeldung/test <benutzer>        Probe-Push nur an dieses Konto (legt keine Eilmeldung an)
 * /app/yii eilmeldung/selbsttest             Regeln prüfen (in einer Transaktion, wird zurückgerollt)
 */
class EilmeldungController extends Controller
{
    public function actionNina($dryRun = 0)
    {
        if (!Yii::$app->mutex->acquire('eilmeldung-nina', 0)) {
            return ExitCode::OK;
        }
        try {
            $log = Nina::abgleich((bool)$dryRun);
        } finally {
            Yii::$app->mutex->release('eilmeldung-nina');
        }
        foreach ($log as $zeile) {
            $this->stdout(date('Y-m-d H:i') . " $zeile\n");
        }
        if ($dryRun && !$log) {
            $this->stdout("nichts Neues (Probelauf)\n");
        }
        return ExitCode::OK;
    }

    public function actionListe()
    {
        foreach (Eilmeldung::aktiv()->all() as $e) {
            $this->stdout("#{$e->id} {$e->art} bis {$e->gueltig_bis} ({$e->quelle}, Push an {$e->empfaenger}): {$e->titel}\n");
        }
        return ExitCode::OK;
    }

    public function actionTest($username)
    {
        $user = User::findOne(['username' => $username]);
        if ($user === null) {
            $this->stderr("Konto $username fehlt\n");
            return ExitCode::DATAERR;
        }
        EilmeldungNeu::instance()->payload(['id' => 0, 'art' => 'sonstiges', 'titel' => 'Test – so sieht eine Eilmeldung aus'])->send($user);
        $this->stdout("Probe-Push an {$user->username} geschickt\n");
        return ExitCode::OK;
    }

    public function actionSelbsttest()
    {
        $fehler = 0;
        $pruefen = function (string $name, $soll, $ist) use (&$fehler) {
            $this->stdout(($soll === $ist ? 'ok   ' : 'FEHL ') . "$name (soll " . var_export($soll, true) . ', ist ' . var_export($ist, true) . ")\n");
            $fehler += $soll !== $ist;
        };
        $pruefen('DWD Moderate (z. B. Frost) kein Push', false, Nina::relevant(['provider' => 'DWD', 'severity' => 'Moderate', 'msgType' => 'Alert']));
        $pruefen('DWD Severe (Unwetter)', true, Nina::relevant(['provider' => 'DWD', 'severity' => 'Severe', 'msgType' => 'Alert']));
        $pruefen('MOWAS Moderate', true, Nina::relevant(['provider' => 'MOWAS', 'severity' => 'Moderate', 'msgType' => 'Alert']));
        $pruefen('Polizei Minor', false, Nina::relevant(['provider' => 'POLICE', 'severity' => 'Minor', 'msgType' => 'Alert']));
        $pruefen('Entwarnung (Cancel)', false, Nina::relevant(['provider' => 'MOWAS', 'severity' => 'Extreme', 'msgType' => 'Cancel']));
        $liste = Versand::module()->empfaenger;
        $pruefen('Empfänger im Testbetrieb', $liste === null ? null : count($liste), $liste === null ? null : (int)Versand::empfaenger()->count());

        $tx = Yii::$app->db->beginTransaction();
        try {
            $neu = fn(array $a) => (new Eilmeldung($a + ['art' => 'sperrung', 'titel' => 'Selbsttest', 'quelle' => 'hand', 'created_at' => date('Y-m-d H:i:s')]))->save(false);
            $vorher = (int)Eilmeldung::aktiv()->count();
            $neu(['gueltig_bis' => date('Y-m-d H:i:s', strtotime('+1 hour'))]);
            $neu(['gueltig_bis' => date('Y-m-d H:i:s', strtotime('-1 hour'))]);
            $neu(['gueltig_bis' => date('Y-m-d H:i:s', strtotime('+1 hour')), 'beendet_am' => date('Y-m-d H:i:s')]);
            $pruefen('nur laufende (nicht abgelaufen, nicht beendet)', $vorher + 1, (int)Eilmeldung::aktiv()->count());
            $e = new Eilmeldung(['art' => 'gibtsnicht', 'titel' => '', 'gueltig_bis' => 'morgen']);
            $pruefen('Formular prüft Art, Titel, Datum', ['art', 'gueltig_bis', 'titel'], ($e->validate() ? [] : (function ($k) { sort($k); return $k; })(array_keys($e->getErrors()))));
        } finally {
            $tx->rollBack();
        }
        $this->stdout($fehler ? "$fehler Fehler\n" : "alles ok (zurückgerollt)\n");
        return $fehler ? ExitCode::UNSPECIFIED_ERROR : ExitCode::OK;
    }
}
