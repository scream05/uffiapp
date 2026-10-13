<?php

// Kopie von calendar/views/common/defaultConfig.php (HumHub-Kalendermodul 1.8.21, AGPL-3.0) ohne Reiter „Erinnerung“.
// Bei Updates des Kalendermoduls mit dem Original abgleichen.

/**
 * @link https://www.humhub.org/
 * @copyright Copyright (c) 2017 HumHub GmbH & Co. KG
 * @license https://www.humhub.com/licences
 */

use humhub\modules\calendar\assets\CalendarBaseAssets;
use humhub\modules\calendar\models\DefaultSettings;
use humhub\modules\calendar\widgets\ContainerConfigMenu;
use humhub\modules\calendar\widgets\GlobalConfigMenu;
use humhub\widgets\bootstrap\Button;
use humhub\widgets\bootstrap\Tabs;
use humhub\widgets\form\ActiveForm;
use yii\web\View;

/* @var $this View */
/* @var $model DefaultSettings */

CalendarBaseAssets::register($this);

?>

<div class="panel panel-default">

    <div class="panel-heading"><?= Yii::t('CalendarModule.config', '<strong>Calendar</strong> module configuration'); ?></div>

    <?php if ($model->isGlobal()) : ?>
        <?= GlobalConfigMenu::widget() ?>
    <?php else: ?>
        <?= ContainerConfigMenu::widget() ?>
    <?php endif; ?>


    <?php $form = ActiveForm::begin(['action' => $model->getSubmitUrl()]); ?>

    <?= Tabs::widget([
        'viewPath' => '@calendar/views/common',
        'isSubMenu' => true,
        'params' => [
            'form' => $form,
            'basicSettings' => $model->basicSettings,
            'participationSettings' => $model->participationSettings,
            'reminderSettings' => $model->reminderSettings,
            'fullCalendarSettings' => $model->fullCalendarSettings,
        ],
        'items' => [
            ['label' => Yii::t('CalendarModule.config', 'Basic'), 'view' => '_settings_basic', 'linkOptions' => ['class' => 'tab-basic']],
            ['label' => Yii::t('CalendarModule.config', 'Participation'), 'view' => '_settings_participation', 'linkOptions' => ['class' => 'tab-participation']],
            // Reiter „Erinnerung“ entfernt: Kalender-Erinnerungen sind systemweit aus (designwahl)
            ['label' => Yii::t('CalendarModule.config', 'Full calendar'), 'view' => '_settings_full_calendar', 'linkOptions' => ['class' => 'tab-full-calendar']],
        ]
    ]); ?>

    <hr>

    <div class="panel-body">
        <?= Button::primary(Yii::t('base', 'Save'))->submit() ?>
    </div>
    <?php ActiveForm::end(); ?>
</div>
