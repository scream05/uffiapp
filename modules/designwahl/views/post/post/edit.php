<?php

/* Nachbau von post/views/post/edit.php: Richtext-Editor mit Werkzeugleiste wie bei Umfragen, keine Anhänge
   (eingebunden über Events::onControllerBeforeAction) */

use humhub\helpers\Html;
use humhub\modules\content\widgets\richtext\RichTextField;
use humhub\modules\post\models\forms\PostEditForm;
use humhub\widgets\bootstrap\Button;
use humhub\widgets\form\ActiveForm;

/* @var $model PostEditForm */
/* @var $submitUrl string */
/* @var $viewContext string */
?>
<div class="content content_edit" id="post_edit_<?= $model->post->id; ?>">
    <?php $form = ActiveForm::begin(['id' => 'post-edit-form_' . $model->post->id]); ?>

    <?= $form->field($model->post, 'message')->widget(RichTextField::class, [
        'id' => 'post_input_' . $model->post->id,
        'layout' => RichTextField::LAYOUT_BLOCK,
        'exclude' => ['upload', 'file_handler'],
        'focus' => true,
        'pluginOptions' => ['maxHeight' => '300px'],
        'placeholder' => Yii::t('PostModule.base', 'Edit your post...'),
    ])->label(false) ?>

    <div class="text-end">
        <?= Button::accent(Yii::t('base', 'Save'))
            ->icon('send')
            ->action('editSubmit', $submitUrl)
            ->sm()
            ->submit() ?>
    </div>

    <?= Html::hiddenInput('viewContext', $viewContext) ?>

    <?php ActiveForm::end(); ?>
</div>
