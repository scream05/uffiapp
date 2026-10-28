<?php

// Nur gemeinsame Basisklassen für die Verzeichnisse „gewerbe“ und „vereine“, keine eigenen Seiten oder Ereignisse.
// Muss nicht aktiviert sein: HumHub registriert den Namensraum jedes vorhandenen Moduls.
return [
    'id' => 'verzeichnis',
    'class' => 'humhub\modules\verzeichnis\Module',
    'namespace' => 'humhub\modules\verzeichnis',
];
