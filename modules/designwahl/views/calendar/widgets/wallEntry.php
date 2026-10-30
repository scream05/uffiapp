<?php

// Kopie von calendar/widgets/views/wallEntry.php (HumHub-Kalendermodul 1.8.21, AGPL-3.0): Teilnahme-Knöpfe
// mit eigenen Klassen (cal-antwort-ja/-vielleicht/-nein) für die Gestaltung in resources/kalender.css;
// kein Blatt-Symbol (file-text) vor dem Datum.
// Seit 30.10.2026 Aufbau wie im Entwurf, aber Plakat unten (Wunsch Nutzer: am Handy zuerst die Angaben als Text):
// Datum/Uhrzeit, Ort mit Link „Auf der Karte“, Gruppe; Knöpfe „In meinen Kalender“ (/designwahl/kalender/ics, inline,
// klappt auch in der iPhone-Web-App), „Google Kalender“, „Per WhatsApp teilen“; dann Beschreibung. Angehängte Bilder
// (Plakat) setzt HumHub ohnehin unter den Inhalt. Gestaltung: resources/kalender/kalender.css (dn-termin-*).
// Bei Updates des Kalendermoduls mit dem Original abgleichen.

use humhub\modules\calendar\helpers\RecurrenceHelper;
use humhub\modules\calendar\models\CalendarEntry;
use humhub\modules\calendar\models\CalendarEntryParticipant;
use humhub\modules\calendar\widgets\EntryParticipants;
use humhub\helpers\DeviceDetectorHelper;
use humhub\helpers\Html;
use humhub\modules\content\widgets\richtext\RichText;
use humhub\modules\file\widgets\FilePreview;
use humhub\modules\ui\icon\widgets\Icon;
use humhub\widgets\bootstrap\Badge;
use yii\helpers\Url;
use yii\web\View;

/* @var $calendarEntry CalendarEntry */
/* @var $stream boolean */
/* @var $collapse boolean */
/* @var $this View */

$color = 'var(--text-color-secondary)';

// Angaben für Knöpfe und Gruppe
$dnTyp = $calendarEntry->getEventType();
$dnUrl = Url::to($calendarEntry->getUrl(), true);
$dnOrt = $calendarEntry->online ? '' : trim((string)$calendarEntry->location);
$dnUtc = fn(DateTimeInterface $d) => (clone $d)->setTimezone(new DateTimeZone('UTC'))->format('Ymd\THis\Z');
if ($calendarEntry->isAllDay()) {
    $dnGoogleZeit = $calendarEntry->getStartDateTime()->format('Ymd') . '/' . $calendarEntry->getEndDateTime()->modify('+1 day')->format('Ymd');
} else {
    $dnGoogleZeit = $dnUtc($calendarEntry->getStartDateTime()) . '/' . $dnUtc($calendarEntry->getEndDateTime());
}
$dnGoogle = 'https://calendar.google.com/calendar/render?' . http_build_query([
    'action' => 'TEMPLATE', 'text' => $calendarEntry->title, 'dates' => $dnGoogleZeit,
    'location' => $dnOrt, 'details' => $dnUrl,
]);
$dnText = $calendarEntry->title . ' – ' . strip_tags($calendarEntry->getFormattedTime()) . ($dnOrt !== '' ? ', ' . $dnOrt : '') . "\n" . $dnUrl;
// Die HumHub-App öffnet wa.me-Links in ihrem eigenen Browser; whatsapp:// gibt sie direkt an WhatsApp weiter
$dnInApp = DeviceDetectorHelper::isAppRequest();
$dnWhatsapp = ($dnInApp ? 'whatsapp://send?text=' : 'https://wa.me/?text=') . rawurlencode($dnText);
$dnNeu = ['target' => '_blank', 'rel' => 'noopener', 'data-pjax-prevent' => 1];
?>

<div class="event calendar-wall-entry mt-3" data-action-component="calendar.CalendarEntry"
     data-calendar-entry="<?= $calendarEntry->id ?>">
    <div class="dn-termin-fakten">
        <div class="dn-termin-fakt">
            <?= Icon::get('clock-o')->color($color)->fixedWith() ?>
            <div>
                <strong>
                    <?= ($calendarEntry->closed) ? '<s>' : '' ?>
                    <?= $calendarEntry->getFormattedTime() ?>
                    <?= ($calendarEntry->closed) ? '</s>' : '' ?>
                </strong>
                <?php if (!$calendarEntry->closed && $calendarEntry->isAllDay()) : ?>
                    <small>(<?= Yii::t('CalendarModule.base', 'All Day') ?>)</small>
                <?php endif; ?>
                <?php if (RecurrenceHelper::isRecurrentRoot($calendarEntry)) : ?>
                    <small>(<?= Yii::t('CalendarModule.base', 'Recurring') ?>)</small>
                <?php endif; ?>
                <?php if ($calendarEntry->closed) : ?>
                    &nbsp;<?= Badge::danger(Yii::t('CalendarModule.base', 'canceled')) ?>
                <?php endif; ?>
            </div>
        </div>
        <?php if ($calendarEntry->hasLocation() || $calendarEntry->online) : ?>
            <div class="dn-termin-fakt">
                <?= Icon::get('map-marker')->color($color)->fixedWith() ?>
                <div>
                    <?= $calendarEntry->online ? Yii::t('CalendarModule.base', 'Online') : $calendarEntry->getLocation(true) ?>
                    <?php if ($dnOrt !== '' && !preg_match('~^https?://~', $dnOrt)) : ?>
                        · <?= Html::a(Yii::t('DesignwahlModule.base', 'Auf der Karte'),
                            'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($dnOrt), $dnNeu) ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
        <?php if (!$dnTyp->isNewRecord) : ?>
            <div class="dn-termin-fakt">
                <span class="dn-termin-punkt" style="--c:<?= Html::encode($dnTyp->color) ?>" aria-hidden="true"></span>
                <div class="dn-termin-gruppe" style="--c:<?= Html::encode($dnTyp->color) ?>"><?= Html::encode(Yii::t('DesignwahlModule.base', $dnTyp->name)) ?></div>
            </div>
        <?php endif; ?>
    </div>

    <?php if (!$calendarEntry->closed && !$calendarEntry->isPast()) : ?>
        <div class="dn-termin-aktionen">
            <?= Html::a(Icon::get('calendar-plus-o') . ' ' . Yii::t('DesignwahlModule.base', 'In meinen Kalender'),
                ['/designwahl/kalender/ics', 'id' => $calendarEntry->id], ['class' => 'dn-termin-knopf dn-termin-haupt'] + $dnNeu) ?>
            <?= Html::a(Icon::get('whatsapp') . ' ' . Yii::t('DesignwahlModule.base', 'Per WhatsApp teilen'), $dnWhatsapp,
                ['class' => 'dn-termin-knopf', 'data-pjax-prevent' => 1] + ($dnInApp ? [] : ['target' => '_blank', 'rel' => 'noopener'])) ?>
            <?= Html::a(Icon::get('google') . ' ' . Yii::t('DesignwahlModule.base', 'Google Kalender'), $dnGoogle,
                ['class' => 'dn-termin-knopf'] + $dnNeu) ?>
        </div>
    <?php endif; ?>

    <?php // Teilnehmen / Vielleicht / Absagen direkt unter den Knöpfen (vor der Beschreibung) ?>
    <?php if ($calendarEntry->participation->canRespond(Yii::$app->user->identity)): ?>
        <div class="event-participation-buttons clearfix">
            <?php if ($calendarEntry->participation->isInvited() && !$calendarEntry->isPast()) : ?>
                <div class="text-warning"><?= Icon::get('warning') . Yii::t('CalendarModule.views', 'You are invited, please select your role:') ?></div>
            <?php endif ?>

            <?php if ($calendarEntry->participation->maxParticipantCheck() || $calendarEntry->participation->isParticipant(Yii::$app->user->identity, false)) : ?>
                <?= EntryParticipants::participateButton($calendarEntry, CalendarEntryParticipant::PARTICIPATION_STATE_ACCEPTED, Yii::t('CalendarModule.views', "Attend"))?->cssClass('cal-antwort cal-antwort-ja') ?>
            <?php endif ?>

            <?php if ($calendarEntry->participation->maxParticipantCheck() || $calendarEntry->participation->isParticipant(Yii::$app->user->identity, true)) : ?>
                <?= EntryParticipants::participateButton($calendarEntry, CalendarEntryParticipant::PARTICIPATION_STATE_MAYBE, Yii::t('CalendarModule.views', "Maybe"))?->cssClass('cal-antwort cal-antwort-vielleicht') ?>
            <?php endif ?>

            <?= EntryParticipants::participateButton($calendarEntry, CalendarEntryParticipant::PARTICIPATION_STATE_DECLINED, Yii::t('CalendarModule.views', "Decline"))?->cssClass('cal-antwort cal-antwort-nein') ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($calendarEntry->description)) : ?>
        <div class="event-info-section clearfix mb-2 dn-termin-text">
            <div class="event-info-section-content" data-ui-markdown data-ui-show-more>
                <?= RichText::output($calendarEntry->description) ?>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($calendarEntry->participation->isShowParticipationLink()) : ?>
        <div class="event-info-section clearfix">
            <?= Icon::get('external-link')->color($color)->left()->size(Icon::SIZE_LG)->style('margin-top:2px;')->fixedWith() ?>
            <div class="event-info-section-content">
                <h1>
                    <?= Yii::t('CalendarModule.views', 'Participation link') ?>
                </h1>
                <div>
                    <?= $calendarEntry->getParticipationLink() ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($calendarEntry->participation->isShowParticipationInfo()) : ?>
        <div class="event-info-section clearfix">
            <?= Icon::get('info-circle')->color($color)->left()->size(Icon::SIZE_LG)->style('margin-top:2px;')->fixedWith() ?>
            <div class="event-info-section-content">
                <h1>
                    <?= Yii::t('CalendarModule.views', 'Additional information') ?>
                </h1>
                <div data-ui-markdown data-ui-show-more>
                    <?= RichText::output($calendarEntry->participant_info) ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php if (RecurrenceHelper::isRecurrentInstance($calendarEntry)) : ?>

        <?php /* @var $root CalendarEntry */
        $root = $calendarEntry->getRecurrenceQuery()->getRecurrenceRoot() ?>
        <?php if ($root && $root->fileManager->find()->count()) : ?>
            <div class="event-info-section clearfix">
                <?= Icon::get('files-o')->color($color)->left()->size(Icon::SIZE_LG)->style('margin-top:2px;')->fixedWith() ?>
                <div class="event-info-section-content">
                    <h1>
                        <?= Yii::t('CalendarModule.base', 'Files') ?>
                    </h1>
                    <?= FilePreview::widget(['model' => $calendarEntry->getRecurrenceQuery()->getRecurrenceRoot()]) ?>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <?php if (!$calendarEntry->closed && $calendarEntry->participation->isEnabled()) : ?>
        <div class="event-info-section clearfix">
            <?= Icon::get('users')->color($color)->left()->size(Icon::SIZE_LG)->style('margin-top:2px;')->fixedWith() ?>
            <div class="event-info-section-content">
                <?= EntryParticipants::widget(['calendarEntry' => $calendarEntry]) ?>
            </div>
        </div>
    <?php endif; ?>

</div>
