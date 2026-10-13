<?php
/**
 * @var \humhub\modules\kiAssistent\models\Feedback[] $items
 * @var bool $alle
 * @var array $stats bewertung => Anzahl
 */

use humhub\helpers\Html;
use yii\helpers\Url;

?>
<div class="container">
    <div class="panel panel-default">
        <div class="panel-heading"><strong>Uffi: Bewertungen</strong>
            <span class="text-muted">(👍 <?= (int)($stats[1] ?? 0) ?> · 👎 <?= (int)($stats[-1] ?? 0) ?>, gespeichert <?= \humhub\modules\kiAssistent\models\Feedback::KEEP_DAYS ?> Tage)</span>
        </div>
        <div class="panel-body">
            <p>
                <?= $alle ? Html::a('Nur offene negative Bewertungen', ['bewertungen']) : Html::a('Alle Bewertungen zeigen', ['bewertungen', 'alle' => 1]) ?>
                · <?= Html::a('Zum Chat', ['index']) ?>
            </p>
            <?php if (!$items): ?>
                <p class="text-muted">Keine Einträge.</p>
            <?php endif ?>
            <?php foreach ($items as $fb): ?>
                <div style="border-top:1px solid rgba(0,0,0,.1);padding:10px 0">
                    <div class="text-muted small"><?= $fb->bewertung > 0 ? '👍' : '👎' ?>
                        <?= Yii::$app->formatter->asDatetime($fb->created_at, 'short') ?><?= $fb->erledigt ? ' · erledigt' : '' ?></div>
                    <div><strong>Frage:</strong> <?= Html::encode($fb->frage) ?></div>
                    <div style="white-space:pre-wrap;margin:4px 0"><strong>Antwort:</strong> <?= Html::encode($fb->antwort) ?></div>
                    <?php if ($fb->kommentar): ?>
                        <div><strong>Kommentar:</strong> <?= Html::encode($fb->kommentar) ?></div>
                    <?php endif ?>
                    <?php if (!$fb->erledigt && $fb->bewertung < 0): ?>
                        <?= Html::beginForm(Url::to(['bewertungen', 'alle' => (int)$alle, 'erledigt' => $fb->id]), 'post', ['style' => 'margin-top:6px']) ?>
                        <button type="submit" class="btn btn-light btn-sm">Als erledigt markieren</button>
                        <?= Html::endForm() ?>
                    <?php endif ?>
                </div>
            <?php endforeach ?>
        </div>
    </div>
</div>
