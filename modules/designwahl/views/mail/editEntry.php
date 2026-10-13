<?php

/* Nachbau von mail/views/mail/editEntry.php: einfaches Textfeld statt Richtext-Editor, keine Anhänge
   (eingebunden über Events::onMailControllerBeforeAction) */

use humhub\modules\mail\models\MessageEntry;
use humhub\widgets\bootstrap\Button;
use humhub\widgets\modal\Modal;
use humhub\widgets\modal\ModalButton;

/* @var $entry MessageEntry */
?>

<?php $form = Modal::beginFormDialog([
    'id' => 'mail-edit',
    'title' => Yii::t("MailModule.base", "Edit message entry"),
    'size' => Modal::SIZE_LARGE,
    'footer' =>
        ModalButton::cancel() . ' ' .
        Button::save(Yii::t('base', 'Save'))->submit()->action('mail.conversation.submitEditEntry')->options(['data-entry-id' => $entry->id]) . ' ' .
        Button::danger(Yii::t('base', 'Delete'))->right()->options(['data-entry-id' => $entry->id])
            ->action('mail.conversation.deleteEntry')
            ->confirm(Yii::t('MailModule.base', '<strong>Confirm</strong> message deletion'),
                Yii::t('MailModule.base', 'Do you really want to delete this message?'),
                Yii::t('MailModule.base', 'Delete'),
                Yii::t('MailModule.base', 'Cancel')),
]) ?>
    <?= $form->field($entry, 'content')->textarea([
        'rows' => 5,
        'placeholder' => Yii::t('MailModule.base', 'Edit message...'),
        'class' => 'form-control textfeld-einfach',
        // 16px: iOS zoomt sonst beim Antippen in die Seite
        'style' => 'font-size:16px;resize:vertical',
    ])->label(false) ?>
<?php Modal::endFormDialog(); ?>
