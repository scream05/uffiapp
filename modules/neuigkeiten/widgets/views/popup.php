<?php
/**
 * @var \humhub\modules\neuigkeiten\Module $module
 * @var array[] $eintraege
 */

use humhub\helpers\Html;
use humhub\modules\neuigkeiten\Module;
use yii\helpers\Url;

?>
<div class="nk-popup" id="nk-popup" role="dialog" aria-modal="true" aria-labelledby="nk-popup-titel" hidden
     data-gesehen-url="<?= Html::encode(Url::to(['/neuigkeiten/index/gesehen'])) ?>">
    <div class="nk-popup-hinter" data-nk-zu></div>
    <div class="nk-popup-karte">
        <button type="button" class="nk-popup-x" data-nk-zu aria-label="<?= Yii::t('NeuigkeitenModule.base', 'Schließen') ?>"><i class="fa fa-times" aria-hidden="true"></i></button>
        <div class="nk-popup-kopf">
            <span class="nk-popup-symbol" aria-hidden="true"><i class="fa fa-gift"></i></span>
            <h2 id="nk-popup-titel"><?= Yii::t('NeuigkeitenModule.base', 'Neu für dich') ?></h2>
        </div>
        <ul class="nk-popup-liste">
            <?php foreach ($eintraege as $e): ?>
                <li>
                    <span class="nk-art nk-art-<?= Html::encode($e['art']) ?>"><i class="fa fa-<?= Module::ARTEN[$e['art']][1] ?? 'circle' ?>" aria-hidden="true"></i><?= Html::encode(Module::artLabel($e['art'])) ?></span>
                    <h3><?= Html::encode($module->feld($e, 'titel')) ?></h3>
                    <p><?= Html::encode($module->feld($e, 'text')) ?></p>
                    <?php if (($url = $module->linkUrl($e)) !== null): ?>
                        <?= Html::a(Yii::t('NeuigkeitenModule.base', 'Ansehen') . ' <i class="fa fa-arrow-right" aria-hidden="true"></i>', $url, ['class' => 'nk-ansehen', 'data-nk-gesehen' => 1]) ?>
                    <?php endif ?>
                </li>
            <?php endforeach ?>
        </ul>
        <div class="nk-popup-fuss">
            <?= Html::a(Yii::t('NeuigkeitenModule.base', 'Alle Neuigkeiten'), ['/neuigkeiten/index'], ['class' => 'btn btn-light', 'data-nk-gesehen' => 1]) ?>
            <button type="button" class="btn btn-primary" data-nk-zu><?= Yii::t('NeuigkeitenModule.base', 'Verstanden') ?></button>
        </div>
    </div>
</div>
<?php $this->registerJs('window.nkPopup && window.nkPopup();') ?>
