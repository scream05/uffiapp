<?php
/**
 * Kopie von humhub/modules/user/views/account/delete.php (HumHub 1.19, AGPL), ergänzt um das Häkchen
 * „Auch meine Beiträge … löschen“ (seit 23.11.2026). Ausgewertet in designwahl\Events::onControllerBeforeAction/
 * onControllerAfterAction, gelöscht wird per Job designwahl\jobs\InhalteLoeschen.
 */

use humhub\modules\user\models\forms\AccountDelete;
use humhub\widgets\bootstrap\Alert;
use humhub\widgets\bootstrap\Button;
use humhub\widgets\form\ActiveForm;

/* @var AccountDelete $model */
?>
<?php $this->beginContent('@user/views/account/_userProfileLayout.php') ?>

<strong><?= Yii::t('UserModule.account', 'Are you sure that you want to delete your account?') ?></strong>
<br>
<br>
<?= Alert::danger(Yii::t('UserModule.account', 'All your personal data will be irrevocably deleted.'))->closeButton(false) ?>

<?php $form = ActiveForm::begin() ?>

<?php if ($model->isAttributeRequired('currentPassword')): ?>
    <?= $form->field($model, 'currentPassword')->passwordInput([
        'maxlength' => 45,
        'placeholder' => Yii::t('UserModule.account', 'Enter your password to continue'),
        'autocomplete' => 'current-password',
    ]) ?>
<?php else: ?>
    <?= $form->field($model, 'currentPassword')->hiddenInput()->label(false); ?>
<?php endif ?>

<div class="form-check" style="margin-top:12px">
    <input type="checkbox" class="form-check-input" name="inhalteLoeschen" value="1" id="dw-inhalte-loeschen"
        <?= Yii::$app->request->post('inhalteLoeschen') === '1' ? 'checked' : '' ?>>
    <label class="form-check-label" for="dw-inhalte-loeschen">
        <?= Yii::t('DesignwahlModule.base', 'Auch alle meine Beiträge, Termine, Mängelmeldungen, Kommentare und „Gefällt mir“ löschen') ?>
    </label>
    <div class="form-text"><?= Yii::t('DesignwahlModule.base', 'Ohne Häkchen bleiben deine Beiträge und Kommentare ohne deinen Namen stehen, damit Gespräche verständlich bleiben. Private Nachrichten bleiben bei den Empfängern.') ?></div>
</div>

<br>

<?= Button::danger(Yii::t('UserModule.account', 'Delete account'))
    ->confirm(
        Yii::t('UserModule.account', 'Are you sure that you want to delete your account?'),
        Yii::t('UserModule.account', 'All your personal data will be irrevocably deleted.'),
    )
    ->submit() ?>

<?php ActiveForm::end() ?>

<?php $this->endContent() ?>
