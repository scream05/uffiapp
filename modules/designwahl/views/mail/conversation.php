<?php

/* Nachbau von mail/views/mail/conversation.php: einfaches Textfeld statt Richtext-Editor, keine Anhänge
   (eingebunden über Events::onMailControllerBeforeAction) */

use humhub\components\View;
use humhub\helpers\Html;
use humhub\modules\mail\models\forms\ReplyForm;
use humhub\modules\mail\models\Message;
use humhub\modules\mail\widgets\ConversationHeader;
use humhub\modules\mail\widgets\ConversationTags;
use humhub\modules\mail\widgets\Messages;
use humhub\widgets\bootstrap\Button;
use humhub\widgets\form\ActiveForm;

/* @var $this View */
/* @var $replyForm ReplyForm */
/* @var $messageCount integer */
/* @var $message Message */

?>
<div class="panel panel-default">

    <?php if ($message === null) : ?>

        <div class="panel-body">
            <?= Yii::t('MailModule.base', 'There are no messages yet.'); ?>
        </div>

    <?php else : ?>

        <div id="mail-conversation-header" class="panel-heading">
            <?= ConversationHeader::widget(['message' => $message]) ?>
        </div>

        <?= ConversationTags::widget(['message' => $message]) ?>

        <div class="panel-body conversation-entry-container">

            <div class="conversation-entry-list">
                <?= Messages::widget(['message' => $message]) ?>
            </div>

        </div>

        <div id="'mail-create-form-<?= $message->id ?>" class="panel panel-default mail-message-form content_create">
            <div class="panel-body">
                <?php if ($message->isBlocked()) : ?>
                    <div class="alert alert-danger">
                        <?= Yii::t('MailModule.base', 'You are not allowed to participate in this conversation. You have been blocked by: {userNames}.', [
                            'userNames' => implode(', ', $message->getBlockerNames())
                        ]); ?>
                    </div>
                <?php else : ?>
                    <?php $form = ActiveForm::begin(['enableClientValidation' => false, 'acknowledge' => true]) ?>

                    <?= Html::hiddenInput('showDateBadge', '1') ?>

                    <div class="richtext-create-input-group">

                        <?= $form->field($replyForm, 'message')->textarea([
                            'id' => 'reply-' . time(),
                            'rows' => 2,
                            'placeholder' => Yii::t('MailModule.base', 'Write a message...'),
                            'class' => 'form-control ProsemirrorEditor textfeld-einfach',
                            'data-ui-widget' => 'designwahl.textfeld',
                            // 16px: iOS zoomt sonst beim Antippen in die Seite
                            'style' => 'font-size:16px;resize:vertical;min-height:2.6em',
                        ])->label(false) ?>

                        <div class="richtext-create-buttons">
                            <?= Button::accent()
                                ->cssClass('reply-button')
                                ->submit()
                                ->action('reply', $replyForm->getUrl())
                                ->icon('paper-plane-o')
                                ->sm() ?>
                        </div>
                    </div>
                </div>

                <?php ActiveForm::end(); ?>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <script <?= Html::nonce() ?>>
        humhub.modules.mail.notification.setMailMessageCount(<?= $messageCount ?>);
    </script>
</div>
