<?php

namespace humhub\modules\stadtrat\commands;

use humhub\modules\stadtrat\components\Erklaert;
use humhub\modules\stadtrat\components\Ris;
use humhub\modules\stadtrat\models\Sitzung;
use humhub\modules\stadtrat\notifications\TagesordnungNeu;
use humhub\modules\user\models\User;
use Yii;
use yii\console\Controller;
use yii\console\ExitCode;

/**
 * /app/yii stadtrat/abrufen [1 = Probelauf]   mit dem Ratsinformationssystem abgleichen (Crontab stündlich)
 * /app/yii stadtrat/liste                     kommende Sitzungen
 * /app/yii stadtrat/test <benutzer>           Probe-Push zur nächsten Sitzung nur an dieses Konto
 * /app/yii stadtrat/uffi                      Text, den Uffi bekommt
 * /app/yii stadtrat/entwurf <sitzung>         Kurzfassung aus /data/stadtrat/<sitzung>.pdf entwerfen (sonst über die Seite)
 * /app/yii stadtrat/audio <sitzung>           Aufnahme erzeugen und prüfen
 */
class StadtratController extends Controller
{
    public function actionAbrufen($dryRun = 0)
    {
        if (!Yii::$app->mutex->acquire('stadtrat-abruf', 0)) {
            return ExitCode::OK;
        }
        try {
            $log = Ris::abgleich((bool)$dryRun);
        } catch (\Throwable $e) {
            $this->stderr(date('Y-m-d H:i') . ' Fehler: ' . $e->getMessage() . "\n");
            return ExitCode::UNAVAILABLE;
        } finally {
            Yii::$app->mutex->release('stadtrat-abruf');
        }
        foreach ($log as $zeile) {
            $this->stdout(date('Y-m-d H:i') . " $zeile\n");
        }
        return ExitCode::OK;
    }

    public function actionListe()
    {
        foreach (Sitzung::kommende()->all() as $s) {
            $this->stdout("{$s->datum} {$s->beginn} {$s->gremium} ({$s->id}): " . count($s->getPunkte()) . ' TOP' . ($s->gemeldet_am ? ", gemeldet {$s->gemeldet_am}" : '') . "\n");
        }
        $this->stdout('Abos: ' . Ris::empfaenger()->count() . "\n");
        return ExitCode::OK;
    }

    public function actionTest($username)
    {
        $user = User::findOne(['username' => $username]);
        $s = Sitzung::find()->where(['not', ['tagesordnung' => null]])->orderBy(['datum' => SORT_DESC])->one();
        if ($user === null || $s === null) {
            $this->stderr("Konto $username oder Sitzung mit Tagesordnung fehlt\n");
            return ExitCode::DATAERR;
        }
        TagesordnungNeu::instance()->payload(Ris::payload($s))->send($user);
        $this->stdout("Probe-Push zu {$s->gremium} am {$s->datum} an {$user->username} geschickt\n");
        return ExitCode::OK;
    }

    public function actionEntwurf($id)
    {
        Erklaert::entwurf((int)$id);
        $k = Erklaert::fassung((int)$id);
        $this->stdout(($k->fehler ?: "kurz:\n{$k->kurz}\n\nlang:\n{$k->lang}\n\nvorlese:\n{$k->vorlese}") . "\n");
        return $k->fehler ? ExitCode::UNAVAILABLE : ExitCode::OK;
    }

    public function actionAudio($id)
    {
        Erklaert::audio((int)$id);
        $k = Erklaert::fassung((int)$id);
        $this->stdout(($k->fehler ?: $k->audio_pruefung) . "\n");
        return $k->fehler ? ExitCode::UNAVAILABLE : ExitCode::OK;
    }

    public function actionUffi()
    {
        $this->stdout(Ris::forAssistant() . "\n");
        return ExitCode::OK;
    }
}
