<?php
/**
 * @var \humhub\modules\eilmeldung\models\Eilmeldung[] $aktiv
 * @var \humhub\modules\eilmeldung\models\Eilmeldung[] $frueher
 * @var \humhub\modules\eilmeldung\Module $module
 * @var bool $darfSchreiben
 */

use yii\helpers\Html;
use yii\helpers\Url;

$this->title = Yii::t('EilmeldungModule.base', 'Eilmeldungen');
$karte = function ($e, bool $laeuft) use ($module) {
    $zeit = Yii::t('EilmeldungModule.base', 'seit {von}, gilt bis {bis}', [
        'von' => date('j.n.Y, G:i', strtotime($e->created_at)), 'bis' => date('j.n.Y, G:i', strtotime($e->gueltig_bis))]);
    if (!$laeuft) {
        $zeit = Yii::t('EilmeldungModule.base', 'vom {von}, beendet', ['von' => date('j.n.Y, G:i', strtotime($e->created_at))]);
    }
    return Html::tag('div',
        Html::tag('h2', '<i class="fa fa-' . Html::encode($e->getSymbol()) . '" aria-hidden="true"></i> '
            . Html::encode($module->artName($e->art) . ': ' . $e->titel), ['style' => 'font-size:1.15em;margin:0 0 4px'])
        . Html::tag('div', Html::encode($zeit), ['class' => 'text-muted small'])
        . ($e->text ? Html::tag('p', nl2br(Html::encode($e->text)), ['style' => 'margin:8px 0 0']) : ''),
        ['id' => 'eilmeldung-' . $e->id, 'class' => 'eilmeldung-karte' . ($laeuft ? ' eilmeldung-laeuft' : ''), 'style' => 'padding:12px 0;border-bottom:1px solid #eee']);
};
?>
<div class="container">
    <div class="panel panel-default">
        <div class="panel-heading"><strong><?= Html::encode($this->title) ?></strong>
            <?php if ($darfSchreiben): ?>
                <?= Html::a(Yii::t('EilmeldungModule.base', 'Neue Eilmeldung'), Url::to(['/eilmeldung/verwalten']), ['class' => 'btn btn-primary btn-sm float-end pull-right']) ?>
            <?php endif ?></div>
        <div class="panel-body">
            <p class="text-muted"><?= Yii::t('EilmeldungModule.base', 'Wichtiges für ganz Uffenheim: Straßensperrungen, Wasser, Strom, Unwetter und amtliche Warnungen aus der Warn-App NINA für den Landkreis. Mit Konto kommen neue Eilmeldungen als Push-Nachricht (abbestellen unter {link}).', [
                    'link' => Html::a(Yii::t('EilmeldungModule.base', 'Benachrichtigungen'), ['/notification/user'])]) ?></p>
            <?php if (!$aktiv): ?>
                <p><strong><?= Yii::t('EilmeldungModule.base', 'Gerade gibt es keine Eilmeldungen.') ?></strong></p>
            <?php endif ?>
            <?php foreach ($aktiv as $e): ?><?= $karte($e, true) ?><?php endforeach ?>
            <?php if ($frueher): ?>
                <h3 style="font-size:1em;margin-top:22px"><?= Yii::t('EilmeldungModule.base', 'Frühere Eilmeldungen (30 Tage)') ?></h3>
                <?php foreach ($frueher as $e): ?><?= $karte($e, false) ?><?php endforeach ?>
            <?php endif ?>
            <p class="small text-muted" style="margin-top:16px"><?= Yii::t('EilmeldungModule.base', 'In Notfällen: Notruf 112, Polizei 110. Amtliche Warnungen immer auch in der Warn-App NINA.') ?></p>
        </div>
    </div>
</div>
