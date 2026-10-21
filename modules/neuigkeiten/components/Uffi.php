<?php

namespace humhub\modules\neuigkeiten\components;

use humhub\modules\neuigkeiten\Module;
use Yii;
use yii\helpers\Url;

/** Abschnitt für den KI-Assistenten: alle Neuigkeiten der Plattform (deutsch), neueste zuerst */
class Uffi
{
    public static function forAssistant(): string
    {
        /** @var Module $modul */
        $modul = Yii::$app->getModule('neuigkeiten');
        $lines = [];
        foreach ($modul->eintraege() as $e) {
            if ($e['datum'] > date('Y-m-d')) {
                continue;
            }
            $url = $modul->linkUrl($e);
            $lines[] = '- ' . date('d.m.Y', strtotime($e['datum'])) . ' – ' . (Module::ARTEN[$e['art']][0] ?? $e['art'])
                . ": {$e['titel']} – {$e['text']}"
                . ($url !== null ? ' – ' . Url::to($url, true) : '');
        }
        return "## Neuigkeiten der Plattform (neue Funktionen, Verbesserungen, Fehlerbehebungen; neueste zuerst)\n"
            . 'Seite „Neuigkeiten“: ' . Url::to(['/neuigkeiten/index'], true) . "\n"
            . ($lines ? implode("\n", $lines) : '(keine Einträge)');
    }
}
