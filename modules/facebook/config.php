<?php

return [
    'id' => 'facebook',
    'class' => 'humhub\modules\facebook\Module',
    'namespace' => 'humhub\modules\facebook',
    'consoleControllerMap' => [
        'facebook' => 'humhub\modules\facebook\commands\FacebookController',
    ],
];
