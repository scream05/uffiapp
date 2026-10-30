<?php

// Kopie von calendar/views/view/index.php (HumHub-Kalendermodul 1.9.8, AGPL-3.0) ohne Zeile über dem Kalender
// (Filterleiste, Knöpfe Exportieren/Einstellen) – Wunsch Nutzer 30.10.2026, damit der Kalender weiter oben beginnt.
// Einstellen bleibt über die Space-Einstellungen (Module → Kalender), Abonnieren über den Kasten unter dem Kalender.
// Bei Updates des Kalendermoduls mit dem Original abgleichen.

use humhub\helpers\ThemeHelper;
use humhub\modules\calendar\helpers\Url;
use humhub\modules\calendar\widgets\CalendarTypeLegend;
use humhub\modules\calendar\widgets\FullCalendar;
use humhub\modules\content\components\ContentContainerActiveRecord;

/* @var $show string */
/* @var $canConfigure bool */
/* @var $canAddEntries bool */
/* @var $contentContainer ContentContainerActiveRecord */
?>
<div class="panel panel-default">
    <div class="panel-body">
        <?= FullCalendar::widget([
            'canWrite' => $canAddEntries,
            'loadUrl' => Url::toAjaxLoad($contentContainer),
            'contentContainer' => $contentContainer,
            'show' => $show,
            'aspectRatio' => ThemeHelper::isFluid() ? 2 : 1.7,
        ]) ?>
    </div>
</div>

<?= CalendarTypeLegend::widget(['contentContainer' => $contentContainer]) ?>
