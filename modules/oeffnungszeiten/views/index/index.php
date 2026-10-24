<?php

use humhub\helpers\Html;
use humhub\modules\oeffnungszeiten\components\Zeiten;

/** @var array $orte */

$t = fn(string $text, array $p = []) => Yii::t('OeffnungszeitenModule.base', $text, $p);
$feiertag = Zeiten::feiertag(date('Y-m-d'));
?>
<style <?= Html::nonce() ?>>
    .oez-ort { padding: 14px 0; border-top: 1px solid var(--hh-border-color, #e5e5e5); scroll-margin-top: 90px; }
    .oez-ort:first-child { border-top: 0; padding-top: 0; }
    .oez-kopf { display: flex; flex-wrap: wrap; align-items: baseline; gap: 4px 10px; }
    .oez-kopf h4 { margin: 0; }
    .oez-marke { display: inline-block; padding: 2px 9px; border-radius: 999px; font-size: .85em; background: #ececec; color: #4a4a4a; }
    .oez-marke.oez-offen { background: #dff1e3; color: #1e6b2d; font-weight: 600; }
    .oez-marke.oez-bald { background: #fbefd6; color: #7a4f00; }
    .oez-unter { color: var(--hh-text-color-soft, #6b6b6b); font-size: .9em; margin-top: 2px; }
    .oez-tabelle { margin: 8px 0 4px; border-collapse: collapse; }
    .oez-ort .oez-tabelle th, .oez-ort .oez-tabelle td { font-size: inherit; line-height: 1.5; color: inherit; text-transform: none; letter-spacing: normal; }
    .oez-ort .oez-tabelle th { font-weight: normal; padding: 1px 14px 1px 0; vertical-align: top; max-width: 10em; }
    .oez-nw { white-space: nowrap; }
    .oez-ort .oez-tabelle td { padding: 1px 0; }
    .oez-ort .oez-tabelle tr.oez-heute th, .oez-ort .oez-tabelle tr.oez-heute td { font-weight: 600; }
    .oez-hinweis { color: var(--hh-text-color-soft, #6b6b6b); font-size: .9em; margin: 4px 0 0; }
</style>
<div class="<?= $this->context->contentContainer ? '' : 'container' ?>">
    <div class="panel panel-default">
        <div class="panel-heading"><i class="fa fa-clock-o"></i> <strong><?= $t('Öffnungszeiten') ?></strong></div>
        <div class="panel-body">
            <?php if ($feiertag !== null): ?>
                <div class="alert alert-info"><?= Html::encode($t('Heute ist Feiertag ({name}). Viele Einrichtungen haben geschlossen.', ['name' => $t($feiertag)])) ?></div>
            <?php endif ?>
            <?php foreach ($orte as $id => $ort): ?>
                <?php
                $s = Zeiten::status($ort);
                ?>
                <div class="oez-ort" id="oez-<?= Html::encode($id) ?>">
                    <div class="oez-kopf">
                        <h4><i class="fa fa-<?= Html::encode($ort['icon']) ?>" aria-hidden="true"></i> <?= Html::encode($t($ort['name'])) ?></h4>
                        <span class="oez-marke<?= $s['offen'] ? ' oez-offen' : '' ?><?= $s['bald'] ? ' oez-bald' : '' ?>"><?= Html::encode($s['offen'] ? $t('Jetzt geöffnet') . ' · ' . $s['text'] : $s['text']) ?></span>
                    </div>
                    <div class="oez-unter">
                        <?= implode(' · ', array_filter([
                            !empty($ort['unterzeile']) ? Html::encode($t($ort['unterzeile'])) : '',
                            !empty($ort['adresse']) ? Html::encode($t($ort['adresse'])) : '',
                            !empty($ort['telefon']) ? $t('Tel.') . ' ' . Html::a(Html::encode($ort['telefon']), 'tel:+49' . ltrim(preg_replace('/\D/', '', $ort['telefon']), '0')) : '',
                        ])) ?>
                    </div>
                    <table class="oez-tabelle">
                        <?php foreach (Zeiten::zeilen($ort) as [$tage, $zeit, $gilt]): ?>
                            <tr<?= $gilt && Zeiten::anTag($ort, time()) ? ' class="oez-heute"' : '' ?>>
                                <th><?= Html::encode($tage) ?></th><td><?= implode(', ', array_map(fn($teil) => '<span class="oez-nw">' . Html::encode($teil) . '</span>', explode(', ', $zeit))) ?></td>
                            </tr>
                        <?php endforeach ?>
                    </table>
                    <?php if (($saison = Zeiten::saisonText($ort)) !== null): ?>
                        <p class="oez-hinweis"><i class="fa fa-calendar" aria-hidden="true"></i> <?= Html::encode($saison) ?></p>
                    <?php endif ?>
                    <?php if (($zu = Zeiten::geschlossenText($ort)) !== null): ?>
                        <p class="oez-hinweis"><?= Html::encode($zu) ?></p>
                    <?php endif ?>
                    <?php if (!empty($ort['hinweis'])): ?>
                        <p class="oez-hinweis"><?= Html::encode($t($ort['hinweis'])) ?></p>
                    <?php endif ?>
                    <?php if (!empty($ort['link'])): ?>
                        <p class="oez-hinweis"><?= Html::a(Html::encode($t($ort['link'][0])) . ' <i class="fa fa-external-link" aria-hidden="true"></i>', $ort['link'][1], ['target' => '_blank', 'rel' => 'noopener']) ?></p>
                    <?php endif ?>
                </div>
            <?php endforeach ?>
            <p class="oez-hinweis" style="margin-top:14px">
                <?= $t('Angaben ohne Gewähr, Stand Oktober 2026. Quellen: uffenheim.de und Landkreis Neustadt a.d.Aisch-Bad Windsheim. Sonderschließungen (z. B. an Brückentagen) kennen wir nicht immer.') ?>
            </p>
        </div>
    </div>
</div>
