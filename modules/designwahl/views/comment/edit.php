<?php

use humhub\helpers\Html;
use humhub\modules\content\widgets\richtext\RichTextField;
use humhub\widgets\bootstrap\Button;
use humhub\widgets\form\ActiveForm;

/* Nachbau von comment/views/comment/edit.php: Richtext-Editor mit Werkzeugleiste wie bei Umfragen, ohne Anhänge
   (eingebunden über Events::onControllerBeforeAction) */

/* @var $comment \humhub\modules\comment\models\Comment */

?>
<div class="content_edit input-container" id="comment_edit_<?= $comment->id; ?>">
    <?php $form = ActiveForm::begin(['acknowledge' => true]); ?>
    <?= Html::hiddenInput('objectModel', $objectModel); ?>
    <?= Html::hiddenInput('objectId', $objectId); ?>

    <?= $form->field($comment, 'message')->widget(RichTextField::class, [
        'id' => 'comment_input_' . $comment->id,
        'layout' => RichTextField::LAYOUT_BLOCK,
        'exclude' => ['upload', 'file_handler'],
        'focus' => true,
        'pluginOptions' => ['maxHeight' => '300px'],
        'placeholder' => Yii::t('CommentModule.base', 'Edit your comment...'),
    ])->label(false) ?>

    <div class="text-end">
        <?= Button::accent(Yii::t('base', 'Save'))
            ->icon('send')
            ->cssClass('btn-comment-submit')->sm()
            ->action('editSubmit', $submitUrl)->submit() ?>
    </div>
    <?php ActiveForm::end(); ?>
</div>
