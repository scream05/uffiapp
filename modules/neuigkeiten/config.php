<?php

use humhub\modules\dashboard\widgets\Sidebar;
use humhub\modules\neuigkeiten\Events;
use humhub\widgets\FooterMenu;
use yii\web\Application;

return [
    'id' => 'neuigkeiten',
    'class' => 'humhub\modules\neuigkeiten\Module',
    'namespace' => 'humhub\modules\neuigkeiten',
    'events' => [
        // Popup über die Seitenleiste der Startseite (wird auch beim Wechsel per PJAX neu gezeichnet)
        ['class' => Sidebar::class, 'event' => Sidebar::EVENT_INIT, 'callback' => [Events::class, 'onDashboardSidebarInit']],
        ['class' => Application::class, 'event' => Application::EVENT_BEFORE_REQUEST, 'callback' => [Events::class, 'onBeforeRequest']],
        ['class' => FooterMenu::class, 'event' => FooterMenu::EVENT_INIT, 'callback' => [Events::class, 'onFooterMenuInit']],
    ],
];
