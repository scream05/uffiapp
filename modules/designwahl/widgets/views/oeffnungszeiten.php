<?php
/**
 * Kasten „Öffnungszeiten“ im Abfallkalender (Events::oeffnungszeiten), auch für Gäste.
 * Zeiten aus dem Modul oeffnungszeiten (data/orte.php) – dort pflegen.
 */

use humhub\helpers\Html;
use humhub\modules\oeffnungszeiten\components\Zeiten;

$t = fn(string $text) => Yii::t('OeffnungszeitenModule.base', $text);
$orte = array_intersect_key(Zeiten::modul()->orte(), array_flip(['wertstoffhof', 'kompostplatz']));
?>
<div class="panel panel-default abfall-zeiten">
    <div class="panel-heading">
        <strong><i class="fa fa-clock-o"></i> <?= Yii::t('DesignwahlModule.base', 'Öffnungszeiten') ?></strong>
    </div>
    <div class="panel-body">
        <?php foreach ($orte as $id => $ort) : ?>
            <?php $s = Zeiten::status($ort) ?>
            <div class="abfall-zeiten-ort">
                <div class="abfall-zeiten-name">
                    <?= Html::a(Html::encode($t($ort['name'])), ['/oeffnungszeiten/index', '#' => 'oez-' . $id]) ?>
                    <span class="abfall-zeiten-status<?= $s['offen'] ? ' abfall-zeiten-offen' : '' ?>"><?= Html::encode($s['text']) ?></span>
                </div>
                <div class="text-muted small">
                    <?= Html::encode($t($ort['adresse'])) ?>
                    <?php if ($ort['telefon'] !== '') : ?>
                        · <?= Yii::t('DesignwahlModule.base', 'Tel.') ?> <?= Html::a(Html::encode($ort['telefon']), 'tel:+49' . ltrim(preg_replace('/\D/', '', $ort['telefon']), '0')) ?>
                    <?php endif ?>
                </div>
                <table class="abfall-zeiten-tabelle">
                    <?php foreach (Zeiten::zeilen($ort) as [$tage, $zeit]) : ?>
                        <tr><th><?= Html::encode($tage) ?></th><td><?= Html::encode($zeit) ?></td></tr>
                    <?php endforeach ?>
                </table>
                <?php if (($zu = Zeiten::geschlossenText($ort)) !== null) : ?>
                    <p class="text-muted small"><?= Html::encode($zu) ?></p>
                <?php endif ?>
                <?php if (!empty($ort['hinweis'])) : ?>
                    <p class="text-muted small"><?= Html::encode($t($ort['hinweis'])) ?></p>
                <?php endif ?>
            </div>
        <?php endforeach ?>
        <p class="text-muted small abfall-zeiten-quelle"><?= Yii::t('DesignwahlModule.base', 'Angaben ohne Gewähr, aktuelle Infos beim {link}.', [
            'link' => Html::a(Html::encode(Yii::t('DesignwahlModule.base', 'Landratsamt (Abfallwirtschaft)')), 'https://www.kreis-nea.de/service-themen/abfall-natur-umwelt/abfallentsorgung/annahmestellen-oeffnungszeiten/wertstoffhoefe.html',
                ['target' => '_blank', 'rel' => 'noopener'])]) ?></p>
    </div>
</div>
<?php $this->registerCss(<<<'CSS'
.abfall-zeiten .abfall-zeiten-ort { margin-bottom: 14px; }
.abfall-zeiten .abfall-zeiten-name { font-weight: 600; }
.abfall-zeiten .abfall-zeiten-tabelle { margin: 6px 0; }
.abfall-zeiten .abfall-zeiten-tabelle th { font-weight: normal; padding: 2px 16px 2px 0; vertical-align: top; white-space: nowrap; }
.abfall-zeiten .abfall-zeiten-tabelle td { padding: 2px 0; }
.abfall-zeiten .abfall-zeiten-quelle { margin: 0; }
.abfall-zeiten .abfall-zeiten-status { font-weight: normal; font-size: .85em; color: #6b6b6b; margin-left: 6px; }
.abfall-zeiten .abfall-zeiten-offen { color: #237032; font-weight: 600; }
CSS, [], 'abfall-zeiten') ?>
