<?php

/* Nachbau von questions/views/question/edit.php: einfaches Textfeld statt Richtext-Editor
   (eingebunden über Events::onControllerBeforeAction) */

use humhub\helpers\Html;
use humhub\modules\questions\assets\QuestionsAssets;
use humhub\modules\questions\helpers\Url;
use humhub\modules\questions\models\Question;
use humhub\widgets\bootstrap\Button;
use humhub\widgets\form\ActiveForm;
use yii\web\View;

/* @var View $this */
/* @var Question $question */
/* @var string $context */
/* @var array $options */

QuestionsAssets::register($this);
?>
<?= Html::beginTag('div', $options) ?>

    <?php $form = ActiveForm::begin() ?>

    <?= Html::hiddenInput('context', $context) ?>

    <?= $form->field($question, 'question')->textInput([
            'placeholder' => Yii::t('QuestionsModule.base', 'Question headline...')
        ]) ?>

    <?= $form->field($question, 'description')->textarea([
            'rows' => 4,
            'placeholder' => Yii::t('QuestionsModule.base', 'Question details...'),
            'class' => 'form-control textfeld-einfach',
            // 16px: iOS zoomt sonst beim Antippen in die Seite
            'style' => 'font-size:16px;resize:vertical',
        ]) ?>

    <?= Button::light(Yii::t('QuestionsModule.base', 'Cancel'))
        ->action('cancelEditForm') ?>

    <?= Button::primary(Yii::t('QuestionsModule.base', 'Save'))
        ->action('submitEditForm', Url::toEditQuestion($question))
        ->submit() ?>

    <?php ActiveForm::end() ?>

<?= Html::endTag('div') ?>
