<?php
/**
 * Laufende Spiele mit Liveticker; die Seite lädt diesen Teil jede Minute neu (?teil=live)
 * @var \humhub\modules\vereine\models\VereinSpiel[] $live
 * @var string[] $label
 */

use humhub\helpers\Html;

if (!$live) {
    return;
}
?>
<div class="panel panel-default fvs-live-panel">
    <div class="panel-heading"><strong><span class="fvs-live-punkt" aria-hidden="true"></span> <?= Yii::t('VereineModule.base', 'Jetzt live') ?></strong>
        <span class="text-muted"><?= Yii::t('VereineModule.base', 'aktualisiert sich jede Minute') ?></span></div>
    <div class="panel-body">
        <?php foreach ($live as $s): ?>
            <?= $this->render('_spiel', ['spiel' => $s, 'label' => $label[$s->mannschaft_id] ?? '']) ?>
            <?php if ($s->ticker_status === 'live'): ?>
                <?= $this->render('_bericht', ['spiel' => $s, 'offen' => true]) ?>
            <?php elseif ($s->ticker_status === 'keiner'): ?>
                <p class="text-muted fvs-hinweis"><?= Yii::t('VereineModule.base', 'Zu diesem Spiel gibt es keinen Liveticker. Das Ergebnis folgt nach dem Spiel.') ?></p>
            <?php endif ?>
        <?php endforeach ?>
    </div>
</div>
