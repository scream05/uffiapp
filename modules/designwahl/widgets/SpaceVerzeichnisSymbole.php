<?php

namespace humhub\modules\designwahl\widgets;

use humhub\modules\space\widgets\SpaceDirectoryIcons;

/**
 * Space-Verzeichnis: Karten ohne Mitgliederzahl (das Widget zeigt nur diese Zahl).
 * Ersetzt SpaceDirectoryIcons per DI-Container (Events::onBeforeRequest).
 */
class SpaceVerzeichnisSymbole extends SpaceDirectoryIcons
{
    public function run()
    {
        return '';
    }
}
