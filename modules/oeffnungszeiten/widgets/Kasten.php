<?php

namespace humhub\modules\oeffnungszeiten\widgets;

use humhub\components\Widget;
use humhub\modules\oeffnungszeiten\components\Zeiten;

/** Kasten auf der Startseite: alle Einrichtungen in Saison mit „geöffnet bis …“ / „öffnet …“ */
class Kasten extends Widget
{
    public function run()
    {
        return Zeiten::ortszeit(fn() => $this->kasten());
    }

    private function kasten(): string
    {
        $zeilen = [];
        foreach (Zeiten::modul()->orte() as $id => $ort) {
            $status = Zeiten::status($ort);
            if ($status['saison']) {
                $zeilen[] = ['id' => $id, 'ort' => $ort, 'status' => $status];
            }
        }
        // Geöffnete zuerst, sonst nach der nächsten Öffnung
        usort($zeilen, fn($a, $b) => [$b['status']['offen'], $a['status']['ab']] <=> [$a['status']['offen'], $b['status']['ab']]);
        return $this->render('kasten', ['zeilen' => $zeilen]);
    }
}
