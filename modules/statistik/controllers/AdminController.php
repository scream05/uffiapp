<?php

namespace humhub\modules\statistik\controllers;

use humhub\modules\admin\components\Controller;
use humhub\modules\statistik\components\Bericht;
use Yii;

/** Administration → Statistik (nur Admins, adminOnly der Basisklasse) */
class AdminController extends Controller
{
    public function actionIndex($zeitraum = '30', $von = null, $bis = null)
    {
        if (!in_array($zeitraum, Bericht::ZEITRAEUME, true)) {
            $zeitraum = '30';
        }
        $bericht = Bericht::fuer($zeitraum, $von, $bis);
        return $this->render('index', [
            'bericht' => $bericht,
            'zeitraum' => $von && $bis ? 'eigen' : $zeitraum,
            'k' => $bericht->kennzahlen(),
            'vorher' => $bericht->vorher()->kennzahlen(),
            'konten' => $bericht->konten(),
            'jetzt' => Bericht::jetztAktiv(),
            'verlauf' => $bericht->verlauf(),
            'seiten' => $bericht->seiten(),
            'einstieg' => $bericht->seiten(10, true),
            'bereiche' => $bericht->bereiche(),
            'herkunft' => $bericht->herkunft(),
            'geraete' => $bericht->verteilung('geraet'),
            'browser' => $bericht->verteilung('browser'),
            'systeme' => $bericht->verteilung('system'),
            'zeitmuster' => $bericht->zeitmuster(),
            'modul' => Yii::$app->getModule('statistik'),
        ]);
    }

    /** Tageswerte und Seiten des Zeitraums als CSV (Semikolon, für Excel/LibreOffice) */
    public function actionCsv($zeitraum = '30', $von = null, $bis = null)
    {
        $bericht = Bericht::fuer(in_array($zeitraum, Bericht::ZEITRAEUME, true) ? $zeitraum : '30', $von, $bis);
        $z = ["\u{FEFF}" . Yii::t('StatistikModule.base', 'Zeitraum') . ";$bericht->von;$bericht->bis", '',
            Yii::t('StatistikModule.base', 'Zeit') . ';' . Yii::t('StatistikModule.base', 'Seitenaufrufe') . ';' . Yii::t('StatistikModule.base', 'Besucher')];
        foreach ($bericht->verlauf() as $v) {
            $z[] = "{$v['label']};{$v['aufrufe']};{$v['besucher']}";
        }
        $z[] = '';
        $z[] = Yii::t('StatistikModule.base', 'Seite') . ';Pfad;' . Yii::t('StatistikModule.base', 'Seitenaufrufe') . ';' . Yii::t('StatistikModule.base', 'Besucher');
        foreach ($bericht->seiten(500) as $s) {
            $z[] = self::zelle(Bericht::bezeichnung($s['pfad'])) . ';' . self::zelle($s['pfad']) . ";{$s['aufrufe']};{$s['besucher']}";
        }
        return Yii::$app->response->sendContentAsFile(implode("\r\n", $z) . "\r\n",
            "statistik_{$bericht->von}_{$bericht->bis}.csv", ['mimeType' => 'text/csv; charset=utf-8']);
    }

    /** CSV-Zelle in Anführungszeichen; führendes =+-@ entschärfen (Formel-Einschleusung in Tabellenprogrammen) */
    private static function zelle(string $s): string
    {
        return '"' . str_replace('"', '""', preg_match('/^[=+\-@\t\r]/', $s) ? "'" . $s : $s) . '"';
    }
}
