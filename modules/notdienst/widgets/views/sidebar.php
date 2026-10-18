<?php

use humhub\helpers\Html;
use humhub\modules\notdienst\components\Notdienst;

/** @var array|null $apotheke */
/** @var int|null $bis */
?>
<div class="panel panel-default" id="notdienst-sidebar">
    <div class="panel-heading"><i class="fa fa-medkit"></i> <strong><?= Yii::t('NotdienstModule.base', 'Notdienste') ?></strong></div>
    <div class="panel-body">
        <p style="margin-bottom:6px">
            <?= Yii::t('NotdienstModule.base', 'Notruf') ?> <a href="tel:112"><strong>112</strong></a> · <?= Yii::t('NotdienstModule.base', 'Ärztlicher Bereitschaftsdienst') ?> <a href="tel:116117"><strong>116 117</strong></a>
        </p>
        <?php if ($apotheke !== null): ?>
            <p style="margin-bottom:6px">
                <?= Yii::t('NotdienstModule.base', 'Apotheken-Notdienst bis {zeit}:', ['zeit' => Html::encode(Notdienst::zeit($bis))]) ?><br>
                <strong><?= Html::encode($apotheke['name']) ?></strong>, <?= Html::encode($apotheke['ort']) ?>
                (<?= Html::encode(str_replace('.', ',', (string)$apotheke['km'])) ?> km),
                <a href="<?= Notdienst::tel($apotheke['telefon']) ?>"><?= Html::encode($apotheke['telefon']) ?></a>
            </p>
        <?php endif ?>
        <?= Html::a(Yii::t('NotdienstModule.base', 'Alle Notdienste') . ' <i class="fa fa-angle-right"></i>', ['/notdienst/index']) ?>
    </div>
</div>
