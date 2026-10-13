<?php

/* Nachbau von post/views/post/edit.php: einfaches Textfeld statt Richtext-Editor, keine Anhänge
   (eingebunden über Events::onControllerBeforeAction) */

use humhub\helpers\Html;
use humhub\modules\post\models\forms\PostEditForm;
use humhub\widgets\bootstrap\Button;
use humhub\widgets\form\ActiveForm;

/* @var $model PostEditForm */
/* @var $submitUrl string */
/* @var $viewContext string */
?>
<div class="content content_edit" id="post_edit_<?= $model->post->id; ?>">
    <?php $form = ActiveForm::begin(['id' => 'post-edit-form_' . $model->post->id]); ?>

    <div class="richtext-create-input-group">
        <?= $form->field($model->post, 'message')->textarea([
            'id' => 'post_input_' . $model->post->id,
            'rows' => 4,
            'autofocus' => true,
            'placeholder' => Yii::t('PostModule.base', 'Edit your post...'),
            'class' => 'form-control textfeld-einfach',
            // 16px: iOS zoomt sonst beim Antippen in die Seite
            'style' => 'font-size:16px;resize:vertical',
        ])->label(false) ?>

        <div class="richtext-create-buttons">
            <?= Button::accent()
                ->icon('send')
                ->action('editSubmit', $submitUrl)
                ->cssClass(' btn-comment-submit')->sm()
                ->submit() ?>
        </div>
    </div>

    <?= Html::hiddenInput('viewContext', $viewContext) ?>

    <?php ActiveForm::end(); ?>
</div>
