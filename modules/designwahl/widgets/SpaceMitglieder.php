<?php

namespace humhub\modules\designwahl\widgets;

use humhub\modules\space\widgets\Members;

/**
 * Kasten „Space-Mitglieder“ ohne Mitgliederzahl: gleiche Logik, eigene Ansicht views/members.php
 * (Kopie der HumHub-Ansicht ohne „(Anzahl)“). Ersetzt Members per DI-Container (Events::onBeforeRequest).
 */
class SpaceMitglieder extends Members
{
}
