<?php
/**
 * Kasten „Öffnungszeiten“ im Abfallkalender (Events::oeffnungszeiten), auch für Gäste.
 * Quelle: kreis-nea.de (Wertstoffhöfe) und Mitteilungsblatt Uffenheim 2026/9 – bei Änderungen hier anpassen.
 */

use humhub\helpers\Html;

$uhr = fn(string $von, string $bis) => Yii::t('DesignwahlModule.base', '{von} – {bis} Uhr', ['von' => $von, 'bis' => $bis]);
$orte = [
    [
        'name' => Yii::t('DesignwahlModule.base', 'Wertstoffhof Uffenheim'),
        'adresse' => Yii::t('DesignwahlModule.base', 'Buchenweg 3 (im Kreisbauhof)'),
        'telefon' => '09161 92-6375',
        'zeiten' => [
            [Yii::t('DesignwahlModule.base', 'Montag'), $uhr('09:00', '12:30')],
            [Yii::t('DesignwahlModule.base', 'Mittwoch'), $uhr('11:30', '17:00')],
            [Yii::t('DesignwahlModule.base', 'Freitag'), $uhr('09:00', '15:00')],
            [Yii::t('DesignwahlModule.base', 'Samstag'), $uhr('09:00', '12:30')],
        ],
        'hinweis' => '',
    ],
    [
        'name' => Yii::t('DesignwahlModule.base', 'Kompostplatz Uffenheim'),
        'adresse' => Yii::t('DesignwahlModule.base', '„Bei den drei Kreuzen“, Richtung Custenlohr (bei der Bauschuttdeponie)'),
        'telefon' => '',
        'zeiten' => [
            [Yii::t('DesignwahlModule.base', 'Mo., Di., Mi., Fr.'), Yii::t('DesignwahlModule.base', '{von1} – {bis1} Uhr und {von2} – {bis2} Uhr',
                ['von1' => '08:00', 'bis1' => '12:30', 'von2' => '13:00', 'bis2' => '16:00'])],
            [Yii::t('DesignwahlModule.base', 'Donnerstag'), Yii::t('DesignwahlModule.base', '{von} – {bis} Uhr (vormittags geschlossen)',
                ['von' => '12:30', 'bis' => '17:00'])],
            [Yii::t('DesignwahlModule.base', 'Samstag'), $uhr('09:00', '14:00')],
        ],
        'hinweis' => Yii::t('DesignwahlModule.base', 'Gartenabfälle, Reisig und Baumschnitt. Kleine Mengen Grüngut auch an den Containern am Geckenheimer Steig.'),
    ],
];

?>
<div class="panel panel-default abfall-zeiten">
    <div class="panel-heading">
        <strong><i class="fa fa-clock-o"></i> <?= Yii::t('DesignwahlModule.base', 'Öffnungszeiten') ?></strong>
    </div>
    <div class="panel-body">
        <?php foreach ($orte as $ort) : ?>
            <div class="abfall-zeiten-ort">
                <div class="abfall-zeiten-name"><?= Html::encode($ort['name']) ?></div>
                <div class="text-muted small">
                    <?= Html::encode($ort['adresse']) ?>
                    <?php if ($ort['telefon'] !== '') : ?>
                        · <?= Yii::t('DesignwahlModule.base', 'Tel.') ?> <?= Html::a(Html::encode($ort['telefon']), 'tel:+49' . ltrim(preg_replace('/\D/', '', $ort['telefon']), '0')) ?>
                    <?php endif ?>
                </div>
                <table class="abfall-zeiten-tabelle">
                    <?php foreach ($ort['zeiten'] as [$tag, $zeit]) : ?>
                        <tr><th><?= Html::encode($tag) ?></th><td><?= Html::encode($zeit) ?></td></tr>
                    <?php endforeach ?>
                </table>
                <?php if ($ort['hinweis'] !== '') : ?>
                    <p class="text-muted small"><?= Html::encode($ort['hinweis']) ?></p>
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
CSS, [], 'abfall-zeiten') ?>
