<?php

/* Nachbau von mail/views/mail/create.php: einfaches Textfeld statt Richtext-Editor, keine Anhänge
   (eingebunden über Events::onMailControllerBeforeAction) */

use humhub\helpers\Html;
use humhub\modules\mail\helpers\Url;
use humhub\modules\mail\models\forms\CreateMessage;
use humhub\modules\user\widgets\UserPickerField;
use humhub\widgets\modal\Modal;
use humhub\widgets\modal\ModalButton;

/* @var $model CreateMessage */
/* @var $isTitleEnabled bool */
?>

<?php $form = Modal::beginFormDialog([
    'id' => 'mail-create',
    'title' => Yii::t('MailModule.base', '<strong>New</strong> message'),
    'closable' => false,
    'form' => ['enableClientValidation' => false, 'acknowledge' => true],
    'footer' => ModalButton::cancel() . ' ' . ModalButton::save(Yii::t('MailModule.base', 'Send'))->submit(Url::toCreateConversation()),
]) ?>
    <?= $form->field($model, 'recipient')->widget(UserPickerField::class,
        [
            'url' => Url::toSearchNewParticipants(),
            'placeholder' => Yii::t('MailModule.base', 'Add recipients'),
        ]
    )->label(false) ?>

    <?php if ($isTitleEnabled): ?>
        <?= $form->field($model, 'title')->textInput(['placeholder' => Yii::t('MailModule.base', 'Subject')])->label(false) ?>
    <?php endif; ?>

    <?= $form->field($model, 'message')->textarea([
        'rows' => 5,
        'placeholder' => Yii::t('MailModule.base', 'Write a message...'),
        'class' => 'form-control textfeld-einfach',
        // 16px: iOS zoomt sonst beim Antippen in die Seite
        'style' => 'font-size:16px;resize:vertical',
    ])->label(false) ?>
<?php Modal::endFormDialog(); ?>

<?= Html::script(' $(\'#recipient\').focus();') ?>
