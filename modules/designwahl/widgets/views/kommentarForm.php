<?php

use humhub\helpers\Html;
use humhub\modules\content\widgets\richtext\RichTextField;
use humhub\widgets\bootstrap\Button;
use humhub\widgets\form\ActiveForm;
use yii\helpers\Url;

/* Nachbau von comment/widgets/views/form.php: Richtext-Editor mit Werkzeugleiste wie bei Umfragen, ohne Anhänge */

$submitUrl = Url::to(['/comment/comment/post']);

$placeholder = ($isNestedComment)
    ? Yii::t('CommentModule.base', 'Write a new reply...')
    : Yii::t('CommentModule.base', 'Write a new comment...');
?>

<div id="comment_create_form_<?= $id ?>" class="comment_create content_create" data-ui-widget="comment.Form"
     style="<?php if ($isHidden): ?>display:none<?php endif; ?>">

    <hr>

    <?php $form = ActiveForm::begin([
        'action' => $submitUrl,
        'acknowledge' => true,
    ]) ?>

    <?= Html::hiddenInput('objectModel', $objectModel) ?>
    <?= Html::hiddenInput('objectId', $objectId) ?>

    <?= $form->field($model, 'message')->widget(RichTextField::class, [
        'id' => 'newCommentForm_' . $id,
        'form' => $form,
        'layout' => RichTextField::LAYOUT_BLOCK,
        'exclude' => ['upload', 'file_handler'],
        'pluginOptions' => ['maxHeight' => '300px'],
        'mentioningUrl' => $mentioningUrl ?? null,
        'placeholder' => $placeholder,
        'events' => [
            'scroll-active' => 'comment.scrollActive',
            'scroll-inactive' => 'comment.scrollInactive',
        ],
    ])->label(false) ?>
    <?php /* comment.js ruft nach dem Absenden getUpload().reset() auf */ ?>
    <div class="main_comment_upload" data-ui-widget="designwahl.textfeld" style="display:none"></div>

    <div class="text-end">
        <?= Button::accent(Yii::t('CommentModule.base', 'Send'))
            ->icon('send')
            ->cssClass('btn-comment-submit')->sm()
            ->action('submit', $submitUrl)->submit() ?>
    </div>

    <?php ActiveForm::end() ?>
</div>
