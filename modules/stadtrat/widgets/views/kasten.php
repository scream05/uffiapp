<?php

use humhub\helpers\Html;
use humhub\modules\stadtrat\components\Ris;

/** @var \humhub\modules\stadtrat\models\Sitzung $s */
$t = fn($x, $p = []) => Yii::t('StadtratModule.base', $x, $p);
$punkte = $s->getPunkte();
$zeigen = 5;
?>
<style <?= Html::nonce() ?>>
    #stadtrat-kasten ol { padding-left: 0; list-style: none; margin: 8px 0 0; }
    #stadtrat-kasten li { padding: 3px 0; border-top: 1px solid var(--hh-border-color, #eee); display: flex; gap: 6px; }
    #stadtrat-kasten .sr-nr { color: var(--hh-text-color-soft, #777); min-width: 1.6em; font-variant-numeric: tabular-nums; }
    #stadtrat-kasten .sr-gremium { display: flex; align-items: center; gap: 8px; }
    #stadtrat-kasten .sr-symbol { flex: 0 0 auto; width: 26px; height: 26px; border-radius: 50%; color: #fff; display: inline-flex; align-items: center; justify-content: center; font-size: 13px; }
    #stadtrat-kasten .sr-klein { font-size: 0.85em; color: var(--hh-text-color-soft, #777); }
</style>
<div class="panel panel-default" id="stadtrat-kasten">
    <div class="panel-heading"><i class="fa fa-university"></i> <strong><?= $t('Nächste Sitzung') ?></strong></div>
    <div class="panel-body">
        <div class="sr-gremium"><?= \humhub\modules\stadtrat\models\Sitzung::symbolHtml($s->gremium) ?><strong><?= Html::encode($s->gremium) ?></strong></div>
        <div class="sr-klein"><?= Html::encode($s->tag() . ($s->beginn ? ', ' . $t('{uhr} Uhr', ['uhr' => $s->beginn]) : '')) ?><?= $s->ort ? ' · ' . Html::encode($s->ort) : '' ?></div>
        <?php if ($punkte): ?>
            <ol>
                <?php foreach (array_slice($punkte, 0, $zeigen) as [$nr, $titel]): ?>
                    <li><span class="sr-nr"><?= Html::encode($nr) ?></span><span><?= Html::encode(strtok($titel, "\n")) ?></span></li>
                <?php endforeach ?>
            </ol>
            <?php if (count($punkte) > $zeigen): ?>
                <div class="sr-klein"><?= $t('und {n} weitere Punkte', ['n' => count($punkte) - $zeigen]) ?></div>
            <?php endif ?>
        <?php else: ?>
            <p class="sr-klein" style="margin:8px 0 0"><?= $t('Die Tagesordnung ist noch nicht veröffentlicht.') ?></p>
        <?php endif ?>
        <p style="margin:8px 0 0"><?= Html::a($t('Alle Sitzungen und Tagesordnungen'), Ris::url($s)) ?></p>
    </div>
</div>
