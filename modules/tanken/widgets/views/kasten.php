<?php

use humhub\helpers\Html;
use humhub\modules\tanken\components\Spritpreise;

/** @var array $daten */
/** @var array $liste */
$t = fn($s, $p = []) => Yii::t('TankenModule.base', $s, $p);
$tiefst = array_map(fn($k) => Spritpreise::tiefst($liste, $k), array_combine(array_keys(Spritpreise::SORTEN), array_keys(Spritpreise::SORTEN)));
?>
<style <?= Html::nonce() ?>>
    #tanken-kasten table { width: 100%; font-variant-numeric: tabular-nums; }
    #tanken-kasten th { font-weight: normal; color: var(--hh-text-color-soft, #777); font-size: 0.85em; text-align: right; padding-bottom: 4px; }
    #tanken-kasten th:first-child { text-align: left; }
    #tanken-kasten td { padding: 4px 0; border-top: 1px solid var(--hh-border-color, #eee); vertical-align: top; }
    #tanken-kasten td.tk-p { text-align: right; white-space: nowrap; padding-left: 8px; font-weight: 600; }
    #tanken-kasten td.tk-p.tk-min { color: #2e7d32; }
    #tanken-kasten .tk-zu td { color: var(--hh-text-color-soft, #999); font-weight: normal; }
    #tanken-kasten .tk-ort { font-size: 0.85em; color: var(--hh-text-color-soft, #777); }
    #tanken-kasten .tk-fuss { font-size: 0.8em; color: var(--hh-text-color-soft, #777); margin: 8px 0 0; }
</style>
<div class="panel panel-default" id="tanken-kasten">
    <div class="panel-heading"><i class="fa fa-car"></i> <strong><?= $t('Spritpreise in Uffenheim') ?></strong></div>
    <div class="panel-body">
        <table>
            <thead><tr><th><span class="visually-hidden"><?= $t('Tankstelle') ?></span></th><th>E5</th><th>E10</th><th>Diesel</th></tr></thead>
            <tbody>
            <?php foreach ($liste as $s): ?>
                <tr class="<?= $s['offen'] ? '' : 'tk-zu' ?>">
                    <td><?= Html::encode(Spritpreise::name($s)) ?>
                        <div class="tk-ort"><?= Html::encode($s['strasse']) ?><?= $s['offen'] ? '' : ' · ' . $t('geschlossen') ?></div></td>
                    <?php foreach (array_keys(Spritpreise::SORTEN) as $k): ?>
                        <td class="tk-p<?= $s['offen'] && $s[$k] && $s[$k] == $tiefst[$k] ? ' tk-min' : '' ?>"><?= Spritpreise::preis($s[$k]) ?></td>
                    <?php endforeach ?>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table>
        <p class="tk-fuss">
            <?= $t('Stand: {zeit}', ['zeit' => Spritpreise::zeit($daten['abgerufen'])]) ?> ·
            <?= $t('Daten: MTS-K über {link}', ['link' => Html::a('Tankerkönig', Spritpreise::QUELLE, ['target' => '_blank', 'rel' => 'noopener'])]) ?>
        </p>
        <?= Html::a($t('Mehr Tankstellen und Tipps') . ' <i class="fa fa-angle-right"></i>', ['/tanken/index']) ?>
    </div>
</div>
