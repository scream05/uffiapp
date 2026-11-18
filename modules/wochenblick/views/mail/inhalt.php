<?php
/**
 * Inhalt des Wochenüberblicks – für die E-Mail (Inline-Stile, $mail = true) und die Seite /wochenblick/ansicht
 * @var array $d Ueberblick::daten()
 * @var bool $mail
 */

use humhub\modules\wochenblick\components\Ueberblick;
use yii\helpers\Html;
use yii\helpers\Url;

$h = 'font-size:16px;font-weight:600;margin:20px 0 6px;color:#2d3e4a';
$li = 'padding:3px 0';
$klein = 'color:#6b6b6b;font-size:13px';
$ul = 'list-style:none;padding:0;margin:0';
$leer = !$d['termine'] && !$d['abfuhr'] && !$d['spiele'] && !$d['wetter'] && !$d['mb'] && !$d['gutscheine'] && !($d['sitzungen'] ?? []);
?>
<div style="font-size:15px;line-height:1.45">
    <p style="margin:0 0 4px"><?= Html::encode(Yii::t('WochenblickModule.base', 'Das steht in Uffenheim an ({von} bis {bis}):', ['von' => Ueberblick::tag($d['von']), 'bis' => Ueberblick::tag($d['bis'])])) ?></p>

    <?php if ($d['wetter']): ?>
        <div style="<?= $h ?>"><?= Html::encode(Yii::t('WochenblickModule.base', 'Wetter am Wochenende')) ?></div>
        <ul style="<?= $ul ?>">
            <?php foreach ($d['wetter'] as [$tag, $symbol, $text, $min, $max, $regen]): ?>
                <li style="<?= $li ?>"><strong><?= Html::encode($tag) ?></strong> <?= $symbol ?> <?= Html::encode($text) ?>, <?= $min ?>–<?= $max ?> °C<?= $regen >= 0.5 ? ', ' . Html::encode(Yii::t('WochenblickModule.base', '{mm} mm Regen', ['mm' => Yii::$app->formatter->asDecimal($regen, 1)])) : '' ?></li>
            <?php endforeach ?>
        </ul>
    <?php endif ?>

    <?php if ($d['termine']): ?>
        <div style="<?= $h ?>"><?= Html::encode(Yii::t('WochenblickModule.base', 'Veranstaltungen')) ?></div>
        <ul style="<?= $ul ?>">
            <?php foreach ($d['termine'] as [$datum, $uhr, $titel, $ort, $url]): ?>
                <li style="<?= $li ?>"><strong><?= Html::encode($datum) ?><?= $uhr !== '' ? ', ' . Html::encode($uhr) : '' ?></strong> · <?= Ueberblick::link($titel, $url) ?><?php if ($ort !== ''): ?> <span style="<?= $klein ?>">(<?= Html::encode($ort) ?>)</span><?php endif ?></li>
            <?php endforeach ?>
        </ul>
        <p style="margin:6px 0 0"><?= Ueberblick::link($d['mehrTermine'] ? Yii::t('WochenblickModule.base', '{n, plural, =1{1 weiterer Termin} other{# weitere Termine}} im Kalender', ['n' => $d['mehrTermine']]) : Yii::t('WochenblickModule.base', 'Alle Termine im Kalender'),
                ($s = \humhub\modules\space\models\Space::findOne(['url' => 'veranstaltungen'])) ? $s->createUrl('/calendar/view/index', [], true) : Url::home(true)) ?></p>
    <?php endif ?>

    <?php if ($d['abfuhr']): ?>
        <div style="<?= $h ?>"><?= Html::encode(Yii::t('WochenblickModule.base', 'Müllabfuhr')) ?> <span style="<?= $klein ?>;font-weight:normal">(<?= Html::encode($d['gebiet'] === 'ortsteil' ? Yii::t('WochenblickModule.base', 'Ortsteile') : Yii::t('WochenblickModule.base', 'Kernstadt')) ?>)</span></div>
        <ul style="<?= $ul ?>">
            <?php foreach ($d['abfuhr'] as [$tag, $text]): ?>
                <li style="<?= $li ?>"><strong><?= Html::encode($tag) ?></strong> · <?= Html::encode($text) ?></li>
            <?php endforeach ?>
        </ul>
    <?php endif ?>

    <?php if ($d['spiele']): ?>
        <div style="<?= $h ?>"><?= Html::encode(Yii::t('WochenblickModule.base', 'Spiele am Wochenende')) ?></div>
        <ul style="<?= $ul ?>">
            <?php foreach ($d['spiele'] as [$tag, $uhr, $text, $zustand]): ?>
                <li style="<?= $li ?>"><strong><?= Html::encode($tag) ?><?= $uhr !== '' ? ', ' . Html::encode($uhr) : '' ?></strong> · <?= Html::encode($text) ?><?php if ($zustand !== ''): ?> <span style="<?= $klein ?>">(<?= Html::encode($zustand) ?>)</span><?php endif ?></li>
            <?php endforeach ?>
        </ul>
    <?php endif ?>

    <?php if ($d['sitzungen'] ?? []): ?>
        <div style="<?= $h ?>"><?= Html::encode(Yii::t('WochenblickModule.base', 'Stadtrat und Ausschüsse')) ?></div>
        <ul style="<?= $ul ?>">
            <?php foreach ($d['sitzungen'] as [$tag, $uhr, $gremium, $top, $url]): ?>
                <li style="<?= $li ?>"><strong><?= Html::encode($tag) ?><?= $uhr !== '' ? ', ' . Html::encode($uhr) : '' ?></strong> · <?= Ueberblick::link($gremium, $url) ?> <span style="<?= $klein ?>">(<?= Html::encode($top ? Yii::t('WochenblickModule.base', '{n, plural, =1{1 Punkt} other{# Punkte}} auf der Tagesordnung', ['n' => $top]) : Yii::t('WochenblickModule.base', 'Tagesordnung folgt')) ?>)</span></li>
            <?php endforeach ?>
        </ul>
    <?php endif ?>

    <?php if ($d['mb']): ?>
        <div style="<?= $h ?>"><?= Html::encode(Yii::t('WochenblickModule.base', 'Mitteilungsblatt')) ?></div>
        <p style="margin:0"><?= Ueberblick::link(Yii::t('WochenblickModule.base', 'Neue Ausgabe {titel} vom {datum}', ['titel' => $d['mb'][0], 'datum' => $d['mb'][1]]), $d['mb'][2]) ?></p>
    <?php endif ?>

    <?php if ($d['gutscheine']): ?>
        <div style="<?= $h ?>"><?= Html::encode(Yii::t('WochenblickModule.base', 'Neue Gutscheine')) ?></div>
        <ul style="<?= $ul ?>">
            <?php foreach ($d['gutscheine'] as [$titel, $betrieb, $bis, $url]): ?>
                <li style="<?= $li ?>"><?= Ueberblick::link($titel, $url) ?> · <?= Html::encode($betrieb) ?> <span style="<?= $klein ?>">(<?= Html::encode(Yii::t('WochenblickModule.base', 'bis {datum}', ['datum' => $bis])) ?>)</span></li>
            <?php endforeach ?>
        </ul>
    <?php endif ?>

    <?php if ($leer): ?>
        <p><?= Html::encode(Yii::t('WochenblickModule.base', 'Diese Woche gibt es nichts Besonderes zu melden.')) ?></p>
    <?php endif ?>

    <?php if ($mail): ?>
        <p style="<?= $klein ?>;margin-top:24px"><?= Yii::t('WochenblickModule.base', 'Du bekommst diesen Überblick, weil du ihn in deinem Konto eingeschaltet hast. Abbestellen oder nur noch als Push: {link}.', [
                'link' => Ueberblick::link(Yii::t('WochenblickModule.base', 'Konto → Wochenüberblick'), Url::to(['/wochenblick/einstellungen'], true))]) ?></p>
    <?php endif ?>
</div>
