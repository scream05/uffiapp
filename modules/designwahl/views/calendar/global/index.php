<?php

// Kopie von calendar/views/global/index.php (HumHub-Kalendermodul 1.9.8, AGPL-3.0) ohne Kopf (Überschrift „Kalender“,
// Knöpfe Exportieren/Einstellen) und ohne Filterleiste – Wunsch Nutzer 30.10.2026, damit der Kalender am Handy
// weiter oben beginnt. Gefiltert wird über die Gruppen-Knöpfe (widgets/views/kalenderLegende.php).
// Bei Updates des Kalendermoduls mit dem Original abgleichen.

use humhub\helpers\ThemeHelper;
use humhub\modules\calendar\helpers\Url;
use humhub\modules\calendar\widgets\CalendarTypeLegend;
use humhub\modules\calendar\widgets\FullCalendar;
use humhub\widgets\FooterMenu;

/* @var $viewMode string */
/* @var $calendars string */
/* @var $show string */
/* @var $types array */
/* @var $editUrl string */

$isFluid = ThemeHelper::isFluid();
$containerClass = $isFluid ? 'container-fluid' : 'container';
$aspectRatio = $isFluid ? 1.9 : 1.5;
?>

<div class="<?= $containerClass ?>">
    <div class="panel panel-default">
        <div class="panel-body">
            <?= FullCalendar::widget([
                'canWrite' => !Yii::$app->user->isGuest,
                'aspectRatio' => $aspectRatio,
                'viewMode' => $viewMode,
                'calendars' => $calendars,
                'show' => $show,
                'types' => $types,
                'loadUrl' => Url::toAjaxLoad(),
                'editUrl' => $editUrl,
            ]) ?>
        </div>
    </div>

    <?= CalendarTypeLegend::widget() ?>
</div>

<?= FooterMenu::widget(['location' => FooterMenu::LOCATION_FULL_PAGE]) ?>
