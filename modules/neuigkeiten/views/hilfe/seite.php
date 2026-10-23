<?php
/**
 * @var string $seite
 * @var string $html Inhalt aus hilfe/<sprache>/<seite>.html (eigener, geprüfter Text)
 */

use humhub\helpers\Html;
use humhub\modules\neuigkeiten\assets\NeuigkeitenAsset;
use humhub\modules\neuigkeiten\Module;

NeuigkeitenAsset::register($this);
?>
<div class="container">
<div class="panel panel-default hl-seite" style="max-width:760px;margin:0 auto">
    <div class="panel-body">
        <nav class="nk-filter hl-reiter" aria-label="<?= Yii::t('NeuigkeitenModule.base', 'Hilfe') ?>">
            <?php foreach (array_keys(Module::HILFE) as $key): ?>
                <?= Html::a(Html::encode(Module::hilfeTitel($key)), ['/neuigkeiten/hilfe/seite', 'seite' => $key],
                    ['class' => $seite === $key ? 'aktiv' : null, 'aria-current' => $seite === $key ? 'page' : null]) ?>
            <?php endforeach ?>
        </nav>
        <?= $html ?>
    </div>
</div>
</div>
