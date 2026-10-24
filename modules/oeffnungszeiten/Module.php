<?php

namespace humhub\modules\oeffnungszeiten;

class Module extends \humhub\components\Module
{
    /** Space, in dessen Menü die Seite verlinkt wird und in dem sie läuft */
    public string $spaceUrl = 'rathaus-uffenheim';

    /** @return array<string, array> Einrichtungen aus data/orte.php, Schlüssel = feste ID */
    public function orte(): array
    {
        static $orte = null;
        return $orte ??= require __DIR__ . '/data/orte.php';
    }
}
