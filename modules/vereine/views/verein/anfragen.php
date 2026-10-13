<?php
/**
 * @var \humhub\modules\vereine\models\VereinVerwalter[] $rows
 */

use humhub\helpers\Html;
use humhub\modules\vereine\models\Verein;

$space = $this->context->contentContainer;
?>
<div class="panel panel-default">
    <div class="panel-heading"><strong>Offene Vereins-Anfragen</strong></div>
    <div class="panel-body">
        <?php if (!$rows): ?>
            <p class="text-muted">Keine offenen Anfragen.</p>
        <?php endif ?>
        <ul class="list-unstyled">
            <?php foreach ($rows as $row): ?>
                <li style="margin-bottom:10px">
                    <?= Html::a(Html::encode($row->user->displayName), $row->user->getUrl()) ?>
                    <?= $row->verein->status === Verein::STATUS_VORGESCHLAGEN ? 'schlägt den Verein' : 'möchte' ?>
                    <strong><?= Html::encode($row->verein->name) ?></strong>
                    <?= $row->verein->status === Verein::STATUS_VORGESCHLAGEN ? 'vor' : 'verwalten' ?>
                    <small class="text-muted">(<?= Yii::$app->formatter->asDate($row->created_at, 'short') ?>)</small>
                    <?php if ($row->nachricht): ?><br><small class="text-muted">„<?= Html::encode($row->nachricht) ?>“</small><?php endif ?>
                    <br><?= Html::a('Prüfen', $space->createUrl('/vereine/verein/verwalter', ['id' => $row->verein_id])) ?>
                    · <?= Html::a('Vereinsseite', $row->verein->getUrl()) ?>
                </li>
            <?php endforeach ?>
        </ul>
    </div>
</div>
