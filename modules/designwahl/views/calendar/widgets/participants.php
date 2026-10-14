<?php

use humhub\modules\calendar\helpers\Url;
use humhub\modules\calendar\models\CalendarEntry;
use humhub\modules\calendar\models\CalendarEntryParticipant;
use humhub\widgets\modal\ModalButton;

/* @var $calendarEntry CalendarEntry */
/* @var $countAttending integer */
/* @var $countMaybe integer */
/* @var $countDeclined integer */
/* @var $countInvited integer */

// Geändert (designwahl): Namen der Teilnehmer nur für alle, die den Termin bearbeiten dürfen, sonst nur die Anzahl
$listeErlaubt = \humhub\modules\designwahl\Events::darfTeilnehmerSehen($calendarEntry->id);
?>

<?php if ($calendarEntry->participation->isEnabled()) : ?>
    <h5 style="margin:0 0 5px;font-weight:600"><?= Yii::t('CalendarModule.views', 'Participants') ?></h5>

    <?php $title = Yii::t('CalendarModule.views', ':count Attending', [':count' => $countAttending]); ?>
    <?php if ($countAttending > 0 && $listeErlaubt) : ?>
        <?= ModalButton::asLink($title)
            ->load(Url::toParticipationUserList($calendarEntry, CalendarEntryParticipant::PARTICIPATION_STATE_ACCEPTED))
            ->action('calendar.editModal') ?>
    <?php else : ?>
        <?= $title; ?>
    <?php endif ?>

    <?php if($calendarEntry->allow_maybe) : ?>
         &middot;
        <?php $title = Yii::t('CalendarModule.views', ':count Undecided', [':count' => $countMaybe]); ?>
        <?php if ($countMaybe > 0 && $listeErlaubt) : ?>
            <?= ModalButton::asLink($title)
                ->load(Url::toParticipationUserList($calendarEntry, CalendarEntryParticipant::PARTICIPATION_STATE_MAYBE))
                ->action('calendar.editModal') ?>
        <?php else : ?>
            <?= $title; ?>
        <?php endif ?>
    <?php endif ?>

    <?php if($calendarEntry->allow_decline) : ?>
         &middot;
        <?php $title = Yii::t('CalendarModule.views', ':count Declined', [':count' => $countDeclined]); ?>
        <?php if ($countDeclined > 0 && $listeErlaubt) : ?>
            <?= ModalButton::asLink($title)
                ->load(Url::toParticipationUserList($calendarEntry, CalendarEntryParticipant::PARTICIPATION_STATE_DECLINED))
                ->action('calendar.editModal') ?>
        <?php else : ?>
            <?= $title; ?>
        <?php endif ?>
    <?php endif ?>

    &middot; <?php $title = Yii::t('CalendarModule.views', ':count Invited', [':count' => $countInvited]); ?>
    <?php if ($countInvited > 0 && $listeErlaubt) : ?>
        <?= ModalButton::asLink($title)
            ->load(Url::toParticipationUserList($calendarEntry, CalendarEntryParticipant::PARTICIPATION_STATE_INVITED))
            ->action('calendar.editModal') ?>
    <?php else : ?>
        <?= $title; ?>
    <?php endif ?>

    <br>
<?php endif; ?>
