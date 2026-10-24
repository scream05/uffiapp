<?php

use humhub\helpers\Html;

/** @var array $zeilen */
?>
<style <?= Html::nonce() ?>>
    #oez-kasten .oez-zeile { display: flex; flex-wrap: wrap; align-items: baseline; column-gap: 8px; padding: 4px 0; }
    #oez-kasten .oez-punkt { flex: 0 0 9px; width: 9px; height: 9px; border-radius: 50%; background: #b5b5b5; transform: translateY(-1px); }
    #oez-kasten .oez-offen .oez-punkt { background: #2e8540; }
    #oez-kasten .oez-bald .oez-punkt { background: #d08700; }
    /* Name nie umbrechen */
    #oez-kasten .oez-name { flex: 1 0 auto; white-space: nowrap; }
    #oez-kasten .oez-status { flex: 0 0 auto; margin-inline-start: auto; white-space: nowrap; text-align: end; font-size: .9em; color: var(--hh-text-color-soft, #6b6b6b); }
    #oez-kasten .oez-offen .oez-status { color: #237032; font-weight: 600; }
    #oez-kasten .oez-bald .oez-status { color: #8a5a00; }
    #oez-kasten .oez-alle { display: inline-block; margin-top: 8px; }
    /* Schmaler Kasten (Handy-Fach): Status einheitlich unter dem Namen */
    #oez-kasten .panel-body { container-type: inline-size; }
    @container (max-width: 380px) {
        #oez-kasten .oez-zeile { padding: 5px 0; }
        #oez-kasten .oez-status { flex-basis: 100%; margin-inline-start: 17px; text-align: start; line-height: 1.3; }
    }
</style>
<div class="panel panel-default" id="oez-kasten">
    <div class="panel-heading"><i class="fa fa-clock-o"></i> <strong><?= Yii::t('OeffnungszeitenModule.base', 'Öffnungszeiten') ?></strong></div>
    <div class="panel-body">
        <?php foreach ($zeilen as $z): ?>
            <?php $s = $z['status'] ?>
            <div class="oez-zeile<?= $s['offen'] ? ' oez-offen' : '' ?><?= $s['bald'] ? ' oez-bald' : '' ?>">
                <span class="oez-punkt" aria-hidden="true"></span>
                <span class="oez-name"><?= Html::a(Html::encode(Yii::t('OeffnungszeitenModule.base', $z['ort']['name'])), ['/oeffnungszeiten/index', '#' => 'oez-' . $z['id']]) ?></span>
                <span class="oez-status"><?= Html::encode($s['text']) ?></span>
            </div>
        <?php endforeach ?>
        <?= Html::a(Yii::t('OeffnungszeitenModule.base', 'Alle Öffnungszeiten') . ' <i class="fa fa-angle-right"></i>', ['/oeffnungszeiten/index'], ['class' => 'oez-alle']) ?>
    </div>
</div>
