<?php
/**
 * @var \humhub\modules\gewerbe\models\Betrieb $betrieb
 * @var \humhub\modules\gewerbe\models\BetriebBeitrag $beitrag
 */

use humhub\helpers\Html;
use humhub\modules\file\widgets\FilePreview;
use humhub\modules\file\widgets\UploadButton;
use humhub\modules\file\widgets\UploadProgress;
use humhub\modules\gewerbe\models\BetriebBeitrag;
use humhub\widgets\form\ActiveForm;

?>
<div class="panel panel-default">
    <div class="panel-body">
        <p><?= Html::a('<i class="fa fa-arrow-left"></i> ' . Html::encode($betrieb->name), $betrieb->getUrl()) ?></p>
        <h1 style="font-size:1.4em;margin-top:0"><?= Yii::t('GewerbeModule.base', 'Beitrag schreiben') ?></h1>
        <p class="help-block"><?= Yii::t('GewerbeModule.base', 'Neuigkeiten, Aktionen, neue Angebote oder geänderte Öffnungszeiten deines Betriebs. Der Beitrag wird vor der Veröffentlichung kurz geprüft; danach erscheint er auf der Betriebsseite und/oder in der Übersicht für alle.') ?></p>
        <?php $form = ActiveForm::begin() ?>
            <?= $form->field($beitrag, 'text')->textarea(['rows' => 8, 'maxlength' => 5000, 'placeholder' => Yii::t('GewerbeModule.base', 'Was gibt es Neues bei euch?')]) ?>
            <div class="form-group">
                <label><?= Yii::t('GewerbeModule.base', 'Bilder (freiwillig)') ?></label><br>
                <?= UploadButton::widget([
                    'id' => 'beitrag-bilder',
                    'submitName' => 'bilderList[]',
                    'label' => Yii::t('GewerbeModule.base', 'Bilder hinzufügen'),
                    'tooltip' => false,
                    'cssButtonClass' => 'btn-light',
                    'progress' => '#beitrag-bilder-progress',
                    'preview' => '#beitrag-bilder-preview',
                    'max' => BetriebBeitrag::MAX_BILDER,
                ]) ?>
                <?= UploadProgress::widget(['id' => 'beitrag-bilder-progress']) ?>
                <?= FilePreview::widget(['id' => 'beitrag-bilder-preview', 'edit' => true, 'options' => ['style' => 'margin-top:10px']]) ?>
                <p class="help-block"><?= Yii::t('GewerbeModule.base', 'Bis zu {anzahl} Bilder. Nur eigene Fotos oder Fotos mit Erlaubnis; bei Personen bitte deren Einverständnis.', ['anzahl' => BetriebBeitrag::MAX_BILDER]) ?></p>
            </div>
            <button type="submit" class="btn btn-primary"><?= Yii::t('GewerbeModule.base', 'Zur Prüfung einreichen') ?></button>
            <?= Html::a(Yii::t('GewerbeModule.base', 'Abbrechen'), $betrieb->getUrl(), ['class' => 'btn btn-link']) ?>
        <?php ActiveForm::end() ?>
    </div>
</div>
