<?php

use humhub\commands\CronController;
use humhub\modules\admin\widgets\AdminMenu;
use humhub\modules\statistik\Events;
use yii\web\Application;

return [
    'id' => 'statistik',
    'class' => 'humhub\modules\statistik\Module',
    'namespace' => 'humhub\modules\statistik',
    'events' => [
        ['class' => Application::class, 'event' => Application::EVENT_AFTER_REQUEST, 'callback' => [Events::class, 'onAfterRequest']],
        ['class' => AdminMenu::class, 'event' => AdminMenu::EVENT_INIT, 'callback' => [Events::class, 'onAdminMenuInit']],
        ['class' => CronController::class, 'event' => CronController::EVENT_ON_DAILY_RUN, 'callback' => [Events::class, 'onCronDaily']],
    ],
];
