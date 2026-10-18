<?php

use humhub\helpers\Html;
use humhub\modules\notdienst\components\Notdienst;

/** @var \humhub\modules\notdienst\Module $module */
/** @var array $periods */
/** @var bool $stale */
/** @var array|null $data */

$this->title = Yii::t('NotdienstModule.base', 'Notdienste');
$now = time();
?>
<style <?= Html::nonce() ?>>
    .nd-apo { padding: 10px 0; border-top: 1px solid var(--hh-border-color, #e5e5e5); }
    .nd-apo:first-child { border-top: 0; }
    .nd-km { color: var(--hh-text-color-soft, #777); white-space: nowrap; margin-left: 6px; }
    .nd-nr { font-size: 1.4em; font-weight: bold; margin-right: 8px; }
    .nd-hint { color: var(--hh-text-color-soft, #777); font-size: 0.9em; }
</style>
<div class="<?= $this->context->contentContainer ? '' : 'container' ?>">
    <div class="panel panel-default">
        <div class="panel-heading"><strong><?= Yii::t('NotdienstModule.base', 'Notrufnummern') ?></strong></div>
        <div class="panel-body">
            <ul class="list-unstyled" style="margin:0">
                <?php foreach ($module->notrufe as [$label, $nr, $hint]): ?>
                    <li style="margin-bottom:8px">
                        <a href="<?= Notdienst::tel($nr) ?>" class="nd-nr"><?= Html::encode($nr) ?></a>
                        <?= Html::encode(Yii::t('NotdienstModule.base', $label)) ?>
                        <?php if ($hint !== ''): ?><br><span class="nd-hint"><?= Html::encode(Yii::t('NotdienstModule.base', $hint)) ?></span><?php endif ?>
                    </li>
                <?php endforeach ?>
                <li><?= Yii::t('NotdienstModule.base', 'Zahnärztlicher Notdienst') ?>: <?= Html::a(Html::encode(parse_url($module->zahnarztUrl, PHP_URL_HOST)), $module->zahnarztUrl, ['target' => '_blank', 'rel' => 'noopener']) ?></li>
            </ul>
        </div>
    </div>

    <div class="panel panel-default">
        <div class="panel-heading"><?= Yii::t('NotdienstModule.base', '<strong>Apotheken-Notdienst</strong> rund um Uffenheim') ?></div>
        <div class="panel-body">
            <?php if ($stale): ?>
                <div class="alert alert-warning">
                    <?= Yii::t('NotdienstModule.base', 'Die Notdienstdaten sind gerade nicht aktuell. Bitte nutze die {link}.', [
                        'link' => Html::a(Yii::t('NotdienstModule.base', 'Notdienstsuche der Landesapothekerkammer'), $module->suchUrl, ['target' => '_blank', 'rel' => 'noopener'])]) ?>
                </div>
            <?php endif ?>
            <?php foreach ($periods as $k => $p): ?>
                <h4 style="margin-top:<?= $k ? '20px' : '0' ?>">
                    <?= Yii::t('NotdienstModule.base', $p['von'] <= $now ? 'Jetzt' : ($k === 1 ? 'Danach' : 'Anschließend')) ?>:
                    <small><?= Yii::t('NotdienstModule.base', '{von} bis {bis}', ['von' => Notdienst::zeit($p['von']), 'bis' => Notdienst::zeit($p['bis'])]) ?></small>
                </h4>
                <div>
                    <?php foreach ($p['apotheken'] as $a): ?>
                        <div class="nd-apo">
                            <strong><?= Html::encode($a['name']) ?></strong>
                            <span class="nd-km"><?= Html::encode(str_replace('.', ',', (string)$a['km'])) ?> km</span><br>
                            <?= Html::a(Html::encode(Notdienst::address($a)), Notdienst::mapUrl($a), ['target' => '_blank', 'rel' => 'noopener']) ?><br>
                            <a href="<?= Notdienst::tel($a['telefon']) ?>"><i class="fa fa-phone"></i> <?= Html::encode($a['telefon']) ?></a>
                        </div>
                    <?php endforeach ?>
                </div>
            <?php endforeach ?>
            <p class="nd-hint" style="margin-top:15px">
                <?= Yii::t('NotdienstModule.base', 'Entfernung über die Straße ab Uffenheim. Vor dem Losfahren am besten kurz anrufen.') ?>
                <?= Yii::t('NotdienstModule.base', 'Weitere Tage und andere Orte: {link}.', ['link' => Html::a(Yii::t('NotdienstModule.base', 'Notdienstsuche der Kammer'), $module->suchUrl, ['target' => '_blank', 'rel' => 'noopener'])]) ?>
                <?php if ($data !== null): ?><?= Yii::t('NotdienstModule.base', 'Stand: {zeit}.', ['zeit' => Notdienst::zeit($data['abgerufen'])]) ?><?php endif ?>
            </p>
            <p class="nd-hint" style="font-size:0.8em">
                <?= Html::encode(Notdienst::QUELLE) ?>
                <?= implode(' · ', array_map(fn($l, $u) => Html::a($l, $u, ['target' => '_blank', 'rel' => 'noopener']), array_keys(Notdienst::QUELLE_LINKS), Notdienst::QUELLE_LINKS)) ?>
            </p>
        </div>
    </div>
</div>
