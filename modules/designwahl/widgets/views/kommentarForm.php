<?php

use humhub\helpers\Html;
use humhub\widgets\bootstrap\Button;
use humhub\widgets\form\ActiveForm;
use yii\helpers\Url;

/* Nachbau von comment/widgets/views/form.php, nur mit <textarea> statt RichTextField und ohne Anhänge */

$submitUrl = Url::to(['/comment/comment/post']);

$placeholder = ($isNestedComment)
    ? Yii::t('CommentModule.base', 'Write a new reply...')
    : Yii::t('CommentModule.base', 'Write a new comment...');
?>

<div id="comment_create_form_<?= $id ?>" class="comment_create content_create kommentar-einfach" data-ui-widget="comment.Form"
     style="<?php if ($isHidden): ?>display:none<?php endif; ?>">

    <hr>

    <?php $form = ActiveForm::begin([
        'action' => $submitUrl,
        'acknowledge' => true,
    ]) ?>

    <?= Html::hiddenInput('objectModel', $objectModel) ?>
    <?= Html::hiddenInput('objectId', $objectId) ?>

    <div class="richtext-create-input-group input-group">
        <?= $form->field($model, 'message')->textarea([
            'id' => 'newCommentForm_' . $id,
            'rows' => 2,
            'placeholder' => $placeholder,
            'class' => 'form-control kommentar-textfeld',
            // 16px: iOS zoomt sonst beim Antippen in die Seite
            'style' => 'font-size:16px;resize:vertical;min-height:2.6em',
        ])->label(false) ?>
        <?php /* comment.js erwartet ein Richtext-Element (getRichtext/clear, Fokus beim Aufklappen) */ ?>
        <div class="humhub-ui-richtext" style="display:none"></div>
        <?php /* … und nach dem Absenden einen Upload-Knopf (getUpload().reset()) */ ?>
        <div class="main_comment_upload" data-ui-widget="designwahl.textfeld" style="display:none"></div>

        <div class="richtext-create-buttons">
            <?= Button::accent()
                ->icon('send')
                ->cssClass('btn-comment-submit')->sm()
                ->action('submit', $submitUrl)->submit() ?>
        </div>
    </div>

    <?php ActiveForm::end() ?>
</div>
