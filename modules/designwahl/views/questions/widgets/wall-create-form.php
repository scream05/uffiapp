<?php

/* Nachbau von questions/widgets/views/wall-create-form.php: Richtext-Editor wie bei Umfragen, ohne Hochladen
   (eingebunden über Events::onControllerBeforeAction) */

use humhub\modules\content\widgets\richtext\RichTextField;
use humhub\modules\content\widgets\WallCreateContentFormFooter;
use humhub\modules\questions\models\Question;
use humhub\widgets\form\ActiveForm;

/* @var $model Question */
/* @var $form ActiveForm */
/* @var $submitUrl string */
?>

<?= $form->field($model, 'question')
    ->textInput(['placeholder' => Yii::t('QuestionsModule.base', 'Question headline...')])
    ->label(false) ?>

<div class="contentForm_options">
    <?= $form->field($model, 'description')->widget(RichTextField::class, [
        'placeholder' => Yii::t('QuestionsModule.base', 'Questions details...'),
        'exclude' => ['upload', 'file_handler'],
    ])->label(false) ?>
</div>

<?= WallCreateContentFormFooter::widget([
    'contentContainer' => $model->content->container,
    'submitUrl' => $submitUrl,
]) ?>
