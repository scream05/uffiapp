<?php

/* Nachbau von questions/widgets/views/answer-form.php: einfaches Textfeld statt Richtext-Editor
   (eingebunden über Events::onControllerBeforeAction) */

use humhub\helpers\Html;
use humhub\modules\questions\helpers\Url;
use humhub\modules\questions\models\QuestionAnswer;
use humhub\widgets\bootstrap\Button;
use humhub\widgets\form\ActiveForm;

/* @var QuestionAnswer $answer */
/* @var array $options */
?>
<?= Html::beginTag('div', $options) ?>
    <?php if ($answer->isNewRecord) : ?>
        <?= Html::a('', null, ['id' => 'create-answer-form', 'class' => 'questions-anchor']) ?>
    <?php endif; ?>

    <?php $form = ActiveForm::begin(['action' => Url::toEditAnswer($answer)]) ?>

    <?= $form->field($answer, 'question_id')->hiddenInput()->label(false) ?>
    <?= $form->field($answer, 'id')->hiddenInput()->label(false) ?>

    <?= $form->field($answer, 'answer')->textarea([
        'id' => 'answerRichTextField' . ($answer->isNewRecord ? 0 : $answer->id),
        'rows' => 3,
        'placeholder' => Yii::t('QuestionsModule.base', 'Provide an answer...'),
        'class' => 'form-control textfeld-einfach',
        // 16px: iOS zoomt sonst beim Antippen in die Seite
        'style' => 'font-size:16px;resize:vertical',
    ])->label($answer->isNewRecord) ?>

    <?php if (!$answer->isNewRecord) : ?>
        <?= Button::light(Yii::t('QuestionsModule.base', 'Cancel'))
            ->action('cancelEdit', Url::toContentAnswer($answer)) ?>
    <?php endif; ?>

    <?= Button::save()->action($answer->isNewRecord ? 'saveAnswer' : 'save') ?>

    <?php ActiveForm::end() ?>

<?= Html::endTag('div') ?>
