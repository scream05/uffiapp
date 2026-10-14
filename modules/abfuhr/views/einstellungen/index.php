<?php
/**
 * @var bool $aktiv
 * @var string $gebiet
 * @var string[] $tonnen
 * @var string $zeit
 * @var array $termine Y-m-d => Tonnen-Schlüssel
 * @var \humhub\modules\abfuhr\Module $module
 */

use humhub\helpers\Html;
use humhub\modules\abfuhr\components\Erinnerung;
use yii\helpers\Url;

?>
<div class="panel-heading"><strong>Abfuhr-Erinnerung</strong></div>
<div class="panel-body">
    <p>Wir erinnern dich rechtzeitig an die Müllabfuhr – als Benachrichtigung hier in der Glocke und, wenn du es auf deinem
        Gerät erlaubt hast, als Push-Nachricht aufs Handy. Eine E-Mail kannst du zusätzlich unter
        <?= Html::a('Benachrichtigungen', ['/notification/user']) ?> („Abfuhr-Erinnerung“) einschalten.</p>

    <?= Html::beginForm(['index'], 'post') ?>
    <div class="checkbox" style="margin-bottom:14px">
        <label style="font-weight:600"><?= Html::checkbox('aktiv', $aktiv) ?> Erinnerung einschalten</label>
    </div>

    <div class="mb-3">
        <label class="control-label">Wo wohnst du?</label>
        <div class="help-block" style="margin-top:0">Restmüll, Bio- und Papiertonne sind in ganz Uffenheim gleich, nur die Gelbe Tonne kommt in den Ortsteilen an einem anderen Tag.</div>
        <div class="radio"><label><?= Html::radio('gebiet', $gebiet === 'kernstadt', ['value' => 'kernstadt']) ?> Kernstadt Uffenheim</label></div>
        <div class="radio"><label><?= Html::radio('gebiet', $gebiet === 'ortsteil', ['value' => 'ortsteil']) ?>
                Ortsteil (Brackenlohr, Custenlohr mit Vorder-/Hinterpfeinach, Langensteinach, Rudolzhofen, Uttenhofen, Wallmersbach, Welbhausen)</label></div>
    </div>

    <div class="mb-3">
        <label class="control-label">Woran erinnern?</label>
        <?php foreach ($module->tonnen as $key => $label): ?>
            <div class="checkbox"><label><?= Html::checkbox('tonnen[]', in_array($key, $tonnen, true), ['value' => $key]) ?> <?= Html::encode($label) ?></label></div>
        <?php endforeach ?>
    </div>

    <div class="mb-3">
        <label class="control-label">Wann?</label>
        <?php foreach ($module->zeiten as $key => [$label]): ?>
            <div class="radio"><label><?= Html::radio('zeit', $zeit === (string)$key, ['value' => $key]) ?> <?= Html::encode($label) ?></label></div>
        <?php endforeach ?>
    </div>

    <button type="submit" class="btn btn-primary">Speichern</button>
    <?= Html::endForm() ?>

    <hr>
    <strong>Deine nächsten Abfuhren</strong> <small class="text-muted">(<?= $gebiet === 'ortsteil' ? 'Ortsteile' : 'Kernstadt' ?>, nächste 4 Wochen)</small>
    <?php if (!$termine): ?>
        <p class="text-muted">Keine Termine gefunden.</p>
    <?php else: ?>
        <ul class="list-unstyled" style="margin-top:6px">
            <?php foreach ($termine as $tag => $keys): ?>
                <?php $keys = array_values(array_intersect($keys, $tonnen ?: array_keys($module->tonnen))); if (!$keys) continue; ?>
                <li style="padding:3px 0"><strong><?= ['So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa'][(int)date('w', strtotime($tag))] . ', ' . date('d.m.', strtotime($tag)) ?></strong> – <?= Html::encode(Erinnerung::liste($keys)) ?></li>
            <?php endforeach ?>
        </ul>
    <?php endif ?>
    <p class="help-block">Quelle: Abfallkalender des Landkreises Neustadt a.d.Aisch-Bad Windsheim; Sonderfälle (z. B. Feiertagsverschiebungen)
        im <?= Html::a('Abfallkalender des Landkreises', 'https://www.kreis-nea.de/lebenslagen/abfallwirtschaft/abfalltermine', ['target' => '_blank', 'rel' => 'noopener']) ?> prüfen.</p>

    <?php if ($aktiv): ?>
        <?= Html::beginForm(['test'], 'post', ['style' => 'margin-top:8px']) ?>
        <button type="submit" class="btn btn-light btn-sm"><i class="fa fa-bell-o"></i> Test-Erinnerung jetzt senden</button>
        <?= Html::endForm() ?>
    <?php endif ?>
</div>
