<?php
/**
 * Gutschein als Karte (Link zur Gutscheinseite)
 * @var \humhub\modules\gewerbe\models\Gutschein $g
 * @var bool $mitBetrieb
 */

use humhub\helpers\Html;
use humhub\modules\gewerbe\components\GutscheinDienst;

$offen = GutscheinDienst::grundAllgemein($g) === null;
?>
<a href="<?= Html::encode($g->getUrl()) ?>" class="gs-ticket<?= $offen ? '' : ' zu' ?>">
    <?php if ($mitBetrieb ?? true): ?>
        <?= $this->render('@gewerbe/views/betrieb/_logo', ['betrieb' => $g->betrieb, 'size' => 44]) ?>
    <?php else: ?>
        <i class="fa fa-ticket fa-2x" aria-hidden="true" style="color:#446e88"></i>
    <?php endif ?>
    <span style="flex:1;min-width:0">
        <span class="gs-titel"><?= Html::encode($g->titel) ?></span>
        <?php if ($mitBetrieb ?? true): ?>
            <span class="gs-betrieb text-muted"><?= Html::encode($g->betrieb->name) ?></span>
        <?php endif ?>
        <span class="gs-stand"><?= Html::encode($g->getStandText()) ?></span>
    </span>
</a>
