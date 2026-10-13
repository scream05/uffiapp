<?php

use humhub\helpers\Html;
use humhub\widgets\bootstrap\Button;
use humhub\widgets\form\ActiveForm;

/* Nachbau von comment/views/comment/edit.php, nur mit <textarea> statt RichTextField und ohne Anhänge
   (eingebunden über Events::onCommentControllerBeforeAction) */

/* @var $comment \humhub\modules\comment\models\Comment */

?>
<div class="content_edit input-container kommentar-einfach" id="comment_edit_<?= $comment->id; ?>">
    <?php $form = ActiveForm::begin(['acknowledge' => true]); ?>
    <?= Html::hiddenInput('objectModel', $objectModel); ?>
    <?= Html::hiddenInput('objectId', $objectId); ?>

    <div class="richtext-create-input-group">
        <?= $form->field($comment, 'message')->textarea([
            'id' => 'comment_input_' . $comment->id,
            'rows' => 3,
            'placeholder' => Yii::t('CommentModule.base', 'Edit your comment...'),
            'class' => 'form-control kommentar-textfeld',
            // 16px: iOS zoomt sonst beim Antippen in die Seite
            'style' => 'font-size:16px;resize:vertical;min-height:2.6em',
        ])->label(false) ?>

        <div class="richtext-create-buttons"><?php
            echo Button::accent()
                ->icon('send')
                ->cssClass('btn-comment-submit')->sm()
                ->action('editSubmit', $submitUrl)->submit();
            ?>
        </div>
    </div>
    <?php ActiveForm::end(); ?>
</div>
