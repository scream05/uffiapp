<?php

/* Nachbau von questions/widgets/views/wall-create-form.php: einfaches Textfeld statt Richtext-Editor
   (eingebunden über Events::onControllerBeforeAction) */

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
    <?= $form->field($model, 'description')->textarea([
        'rows' => 3,
        'placeholder' => Yii::t('QuestionsModule.base', 'Questions details...'),
        'class' => 'form-control textfeld-einfach',
        // 16px: iOS zoomt sonst beim Antippen in die Seite
        'style' => 'font-size:16px;resize:vertical',
    ])->label(false) ?>
</div>

<?= WallCreateContentFormFooter::widget([
    'contentContainer' => $model->content->container,
    'submitUrl' => $submitUrl,
]) ?>
