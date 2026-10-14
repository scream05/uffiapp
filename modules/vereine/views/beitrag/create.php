<?php
/**
 * @var \humhub\modules\vereine\models\Verein $verein
 * @var \humhub\modules\vereine\models\VereinBeitrag $beitrag
 */

use humhub\helpers\Html;
use humhub\modules\file\widgets\FilePreview;
use humhub\modules\file\widgets\UploadButton;
use humhub\modules\file\widgets\UploadProgress;
use humhub\modules\vereine\models\VereinBeitrag;
use humhub\widgets\form\ActiveForm;

?>
<div class="panel panel-default">
    <div class="panel-body">
        <p><?= Html::a('<i class="fa fa-arrow-left"></i> ' . Html::encode($verein->name), $verein->getUrl()) ?></p>
        <h1 style="font-size:1.4em;margin-top:0">Beitrag schreiben</h1>
        <p class="help-block">Neuigkeiten, Rückblicke, Aufrufe deines Vereins. Der Beitrag wird vor der Veröffentlichung kurz geprüft;
            danach erscheint er auf der Vereinsseite und/oder in der Übersicht für alle. Termine trägst du bitte über „Termin eintragen“ ein.</p>
        <?php $form = ActiveForm::begin() ?>
            <?= $form->field($beitrag, 'text')->textarea(['rows' => 8, 'maxlength' => 5000, 'placeholder' => 'Was gibt es Neues im Verein?']) ?>
            <div class="form-group">
                <label>Bilder (freiwillig)</label><br>
                <?= UploadButton::widget([
                    'id' => 'beitrag-bilder',
                    'submitName' => 'bilderList[]',
                    'label' => 'Bilder hinzufügen',
                    'tooltip' => false,
                    'cssButtonClass' => 'btn-light',
                    'progress' => '#beitrag-bilder-progress',
                    'preview' => '#beitrag-bilder-preview',
                    'max' => VereinBeitrag::MAX_BILDER,
                ]) ?>
                <?= UploadProgress::widget(['id' => 'beitrag-bilder-progress']) ?>
                <?= FilePreview::widget(['id' => 'beitrag-bilder-preview', 'edit' => true, 'options' => ['style' => 'margin-top:10px']]) ?>
                <p class="help-block">Bis zu <?= VereinBeitrag::MAX_BILDER ?> Bilder. Nur eigene Fotos oder Fotos mit Erlaubnis; bei Personen bitte deren Einverständnis.</p>
            </div>
            <button type="submit" class="btn btn-primary">Zur Prüfung einreichen</button>
            <?= Html::a('Abbrechen', $verein->getUrl(), ['class' => 'btn btn-link']) ?>
        <?php ActiveForm::end() ?>
    </div>
</div>
