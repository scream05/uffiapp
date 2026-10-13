<?php

/* Nachbau von post/widgets/views/form.php: einfaches Textfeld statt Richtext-Editor
   (eingebunden über Events::onControllerBeforeAction) */

use humhub\modules\content\widgets\WallCreateContentForm;
use humhub\modules\content\widgets\WallCreateContentFormFooter;
use humhub\modules\post\models\Post;
use humhub\widgets\form\ActiveForm;

/* @var WallCreateContentForm $wallCreateContentForm */
/* @var ActiveForm $form */
/* @var Post $post */

$archiviert = property_exists(Yii::$app->controller, 'contentContainer') && Yii::$app->controller->contentContainer->isArchived();
?>

<?= $form->field($post, 'message')->textarea([
    'id' => 'contentForm_message' . ($wallCreateContentForm->isModal ? 'Modal' : ''),
    'rows' => 3,
    'placeholder' => $archiviert
        ? Yii::t("PostModule.base", "This space is archived.")
        : Yii::t("PostModule.base", "What's on your mind?"),
    'disabled' => $archiviert,
    'class' => 'form-control textfeld-einfach',
    // 16px: iOS zoomt sonst beim Antippen in die Seite
    'style' => 'font-size:16px;resize:vertical;max-height:300px',
])->label(false) ?>

<?= WallCreateContentFormFooter::widget([
    'contentContainer' => $post->content->container,
    'wallCreateContentForm' => $wallCreateContentForm,
]) ?>
