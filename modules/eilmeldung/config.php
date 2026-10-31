<?php

use humhub\modules\dashboard\widgets\DashboardContent;
use humhub\modules\eilmeldung\Events;
use humhub\modules\user\widgets\AccountMenu;
use yii\base\Widget;

return [
    'id' => 'eilmeldung',
    'class' => 'humhub\modules\eilmeldung\Module',
    'namespace' => 'humhub\modules\eilmeldung',
    'consoleControllerMap' => [
        'eilmeldung' => 'humhub\modules\eilmeldung\commands\EilmeldungController',
    ],
    'events' => [
        ['class' => DashboardContent::class, 'event' => Widget::EVENT_BEFORE_RUN, 'callback' => [Events::class, 'onDashboardContentBeforeRun']],
        ['class' => AccountMenu::class, 'event' => AccountMenu::EVENT_INIT, 'callback' => [Events::class, 'onAccountMenuInit']],
    ],
];
