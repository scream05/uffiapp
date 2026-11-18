<?php
/** @var \humhub\modules\kurse\models\Kurs $kurs */

use humhub\helpers\Html;

?>
<div class="panel panel-default">
    <div class="panel-heading"><strong><?= Yii::t('KurseModule.base', 'Datenschutzhinweise zur Kursanmeldung') ?></strong></div>
    <div class="panel-body">
        <p><?= Html::a('<i class="fa fa-arrow-left"></i> ' . Html::encode(Yii::t('KurseModule.base', 'Zum Kurs')), $kurs->getUrl()) ?></p>
        <?= $this->render('_datenschutz_text', ['kurs' => $kurs]) ?>
    </div>
</div>
