<?php

namespace humhub\modules\oeffnungszeiten\commands;

use humhub\modules\oeffnungszeiten\components\Zeiten;
use humhub\modules\user\models\User;
use Yii;
use yii\console\Controller;
use yii\console\ExitCode;

/**
 * Nächtlicher Abgleich mit den Quellen (Crontab 4:30, nach stadtwissen.py um 3:30):
 *   /app/yii oeffnungszeiten/pruefen [zeigen]
 * Merkt sich je Einrichtung die Zeilen zu Öffnungszeiten aus wissen/ (Moduleinstellung „pruefung“). Ändern sie sich
 * oder fehlt die Quelle, geht einmalig eine Mail an den Admin (Benutzer-ID 1); danach gilt der neue Stand.
 * Der erste Lauf legt nur den Stand an.
 */
class OeffnungszeitenController extends Controller
{
    public const WISSEN = '/srv/wissen';

    /** Zeilen, die etwas über Öffnungszeiten sagen (Preise ausgenommen) */
    public const RELEVANT = '/\d{1,2}[:.]\d{2}|\d{1,2}\.\d{1,2}\.\d{2,4}|\b(Uhr|geöffnet|geschlossen|öffnet|schließt|Saison|Montag|Dienstag|Mittwoch|Donnerstag|Freitag|Samstag|Sonntag|Feiertag\w*|Januar|Februar|März|April|Mai|Juni|Juli|August|September|Oktober|November|Dezember)\b/u';

    public function actionPruefen($zeigen = 0)
    {
        $modul = Zeiten::modul();
        $alt = json_decode((string)$modul->settings->get('pruefung', '{}'), true) ?: [];
        $neu = [];
        $meldungen = [];
        foreach ($modul->orte() as $id => $ort) {
            if (empty($ort['pruefen'])) {
                continue;
            }
            $zeilen = self::auszug($ort['pruefen']);
            $neu[$id] = $zeilen;
            if ($zeigen) {
                $this->stdout("== $id\n" . implode("\n", $zeilen ?? ['(Quelle fehlt)']) . "\n");
            }
            if (!array_key_exists($id, $alt) || $alt[$id] === $zeilen) {
                continue;
            }
            $meldungen[] = self::meldung($ort, $alt[$id], $zeilen);
        }
        $modul->settings->set('pruefung', json_encode($neu, JSON_UNESCAPED_UNICODE));
        $stand = date('Y-m-d H:i:s') . ': ' . count($neu) . ' Einrichtungen geprüft, ' . count($meldungen) . ' geändert';
        if ($meldungen && !self::mail($meldungen)) {
            $this->stdout("$stand – Mail an den Admin fehlgeschlagen\n");
            $modul->settings->set('pruefung', json_encode($alt, JSON_UNESCAPED_UNICODE)); // morgen erneut melden
            return ExitCode::UNSPECIFIED_ERROR;
        }
        $this->stdout("$stand\n");
        return ExitCode::OK;
    }

    /** Relevante Zeilen aus der Quelle (null = Datei oder Abschnitt fehlt) */
    public static function auszug(array $pruefen): ?array
    {
        $datei = self::WISSEN . '/' . $pruefen['datei'];
        if (!is_file($datei)) {
            return null;
        }
        $zeilen = preg_split('/\R/u', (string)file_get_contents($datei));
        if (preg_match('/^---$/', $zeilen[0] ?? '')) {
            // Kopf von stadtwissen.py (Titel, Quelle, Stand) nicht vergleichen
            $ende = array_search('---', array_slice($zeilen, 1), true);
            $zeilen = $ende === false ? $zeilen : array_slice($zeilen, $ende + 2);
        }
        if (!empty($pruefen['ab'])) {
            $start = null;
            foreach ($zeilen as $i => $z) {
                if (preg_match($pruefen['ab'], $z)) {
                    $start = $i;
                    break;
                }
            }
            if ($start === null) {
                return null;
            }
            $abschnitt = [$zeilen[$start]];
            foreach (array_slice($zeilen, $start + 1) as $z) {
                if (!empty($pruefen['bis']) && preg_match($pruefen['bis'], $z)) {
                    break;
                }
                $abschnitt[] = $z;
            }
            $zeilen = $abschnitt;
        } elseif (!empty($pruefen['bis'])) {
            foreach ($zeilen as $i => $z) {
                if (preg_match($pruefen['bis'], $z)) {
                    $zeilen = array_slice($zeilen, 0, $i);
                    break;
                }
            }
        }
        $treffer = array_values(array_filter(array_map(fn($z) => trim(preg_replace('/\s+/u', ' ', $z)), $zeilen),
            fn($z) => $z !== '' && preg_match($pruefen['nur'] ?? self::RELEVANT, $z) && !preg_match('/EUR|€/u', $z)));
        return $treffer ?: null;
    }

    private static function meldung(array $ort, ?array $vorher, ?array $jetzt): string
    {
        $unsere = array_map(fn($z) => "  {$z[0]}: {$z[1]}", Zeiten::zeilen($ort));
        if (($s = Zeiten::saisonText($ort)) !== null) {
            $unsere[] = "  $s";
        }
        $liste = fn(?array $z) => $z === null ? '  (Quelle oder Abschnitt nicht gefunden – Seite verschoben oder umgebaut?)' : '  ' . implode("\n  ", $z);
        return "{$ort['name']} – {$ort['quelle']}\n\nVorher:\n" . $liste($vorher) . "\n\nJetzt:\n" . $liste($jetzt)
            . "\n\nBei uns eingetragen:\n" . implode("\n", $unsere);
    }

    private static function mail(array $meldungen): bool
    {
        $admin = User::findOne(1);
        if ($admin === null) {
            return false;
        }
        $text = "Bei diesen Einrichtungen haben sich die Angaben zu den Öffnungszeiten auf der Quellseite geändert. "
            . "Bitte prüfen und bei Bedarf in modules/oeffnungszeiten/data/orte.php anpassen (oder Claude Bescheid geben). "
            . "Diese Meldung kommt je Änderung nur einmal.\n\n"
            . implode("\n\n――――――――――\n\n", $meldungen) . "\n";
        return Yii::$app->mailer->compose()
            ->setFrom([Yii::$app->settings->get('mailerSystemEmailAddress') => Yii::$app->settings->get('mailerSystemEmailName')])
            ->setTo($admin->email)
            ->setSubject('Rund um Uffni: Öffnungszeiten geändert (' . count($meldungen) . ')')
            ->setTextBody($text)
            ->send();
    }
}
