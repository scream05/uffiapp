<?php

/* Nachbau von post/widgets/views/form.php: Richtext-Editor mit Werkzeugleiste wie bei Umfragen
   (Layout „block“), ohne Hochladen – Anhänge entfernt Events::nurText() beim Speichern
   (eingebunden über Events::onControllerBeforeAction) */

use humhub\modules\content\widgets\richtext\RichTextField;
use humhub\modules\content\widgets\WallCreateContentForm;
use humhub\modules\content\widgets\WallCreateContentFormFooter;
use humhub\modules\post\models\Post;
use humhub\widgets\form\ActiveForm;

/* @var WallCreateContentForm $wallCreateContentForm */
/* @var string $mentioningUrl */
/* @var ActiveForm $form */
/* @var Post $post */
?>

<?= $form->field($post, 'message')->widget(RichTextField::class, [
    'id' => 'contentForm_message' . ($wallCreateContentForm->isModal ? 'Modal' : ''),
    'form' => $form,
    'layout' => RichTextField::LAYOUT_BLOCK,
    'exclude' => ['upload', 'file_handler'],
    'pluginOptions' => ['maxHeight' => '300px'],
    'placeholder' => Yii::t("PostModule.base", "What's on your mind?"),
    'name' => 'message',
    'disabled' => (property_exists(Yii::$app->controller, 'contentContainer') && Yii::$app->controller->contentContainer->isArchived()),
    'disabledText' => Yii::t("PostModule.base", "This space is archived."),
    'mentioningUrl' => $mentioningUrl ?? null,
])->label(false) ?>

<?= WallCreateContentFormFooter::widget([
    'contentContainer' => $post->content->container,
    'wallCreateContentForm' => $wallCreateContentForm,
]) ?>
