<?php
/**
 * @var \humhub\modules\gewerbe\models\BetriebVerwalter[] $rows
 */

use humhub\helpers\Html;
use humhub\modules\gewerbe\models\Betrieb;

$space = $this->context->contentContainer;
?>
<div class="panel panel-default">
    <div class="panel-heading"><strong>Offene Gewerbe-Anfragen</strong></div>
    <div class="panel-body">
        <?php if (!$rows): ?>
            <p class="text-muted">Keine offenen Anfragen.</p>
        <?php endif ?>
        <ul class="list-unstyled">
            <?php foreach ($rows as $row): ?>
                <li style="margin-bottom:10px">
                    <?= Html::a(Html::encode($row->user->displayName), $row->user->getUrl()) ?>
                    <?= $row->betrieb->status === Betrieb::STATUS_VORGESCHLAGEN ? 'hat den Betrieb' : 'möchte' ?>
                    <strong><?= Html::encode($row->betrieb->name) ?></strong>
                    <?= $row->betrieb->status === Betrieb::STATUS_VORGESCHLAGEN ? 'eingetragen' : 'verwalten' ?>
                    <small class="text-muted">(<?= Yii::$app->formatter->asDate($row->created_at, 'short') ?>)</small>
                    <?php if ($row->nachricht): ?><br><small class="text-muted">„<?= Html::encode($row->nachricht) ?>“</small><?php endif ?>
                    <br><?= Html::a('Prüfen', $space->createUrl('/gewerbe/betrieb/verwalter', ['id' => $row->betrieb_id])) ?>
                    · <?= Html::a('Betriebsseite', $row->betrieb->getUrl()) ?>
                </li>
            <?php endforeach ?>
        </ul>
    </div>
</div>
