<?php
/** @var array $d Ueberblick::daten() */

use yii\helpers\Html;
use yii\helpers\Url;

$this->title = Yii::t('WochenblickModule.base', 'Wochenüberblick');
?>
<div class="container">
    <div class="panel panel-default">
        <div class="panel-heading"><strong><?= Html::encode($this->title) ?></strong>
            <?= Html::a(Yii::t('WochenblickModule.base', 'Einstellungen'), Url::to(['/wochenblick/einstellungen']), ['class' => 'float-end pull-right small']) ?></div>
        <div class="panel-body">
            <?= $this->renderFile('@wochenblick/views/mail/inhalt.php', ['d' => $d, 'mail' => false]) ?>
        </div>
    </div>
</div>
