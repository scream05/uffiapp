<?php

use humhub\modules\kurse\Events;
use humhub\modules\space\widgets\Menu;
use yii\web\Application;

return [
    'id' => 'kurse',
    'class' => 'humhub\modules\kurse\Module',
    'namespace' => 'humhub\modules\kurse',
    'consoleControllerMap' => [
        'kurse' => 'humhub\modules\kurse\commands\KurseController',
    ],
    'events' => [
        ['class' => Menu::class, 'event' => Menu::EVENT_INIT, 'callback' => [Events::class, 'onSpaceMenuInit']],
        // Kurze Adressen der eigenen Kursseiten (/kurse/<slug>)
        ['class' => Application::class, 'event' => Application::EVENT_BEFORE_REQUEST, 'callback' => [Events::class, 'onBeforeRequest']],
        // auch in der Konsole (Cron-Mails mit Links zur Anmeldung)
        ['class' => \yii\console\Application::class, 'event' => \yii\console\Application::EVENT_BEFORE_REQUEST, 'callback' => [Events::class, 'onBeforeRequest']],
    ],
];
