<?php
/**
 * @var \humhub\modules\vereine\models\VereinBeitrag[] $offen
 * @var \humhub\modules\vereine\models\VereinBeitrag[] $erledigt
 */

use humhub\helpers\Html;
use humhub\modules\vereine\models\VereinBeitrag;

$space = $this->context->contentContainer;
?>
<div class="panel panel-default">
    <div class="panel-heading"><strong>Vereins-Beiträge prüfen</strong></div>
    <div class="panel-body">
        <p class="help-block">Beiträge, die Vereinsverwalter eingereicht haben. Freigeben auf der Vereinsseite und/oder in der Übersicht
            (dort als normaler Beitrag im Space „<?= Html::encode($space->name) ?>“, für alle sichtbar, mit Kommentaren). Bei Ablehnung
            geht die Begründung per E-Mail an den Verfasser.</p>
        <?php if (!$offen): ?>
            <p class="text-muted">Zurzeit nichts zu prüfen.</p>
        <?php endif ?>
        <?php foreach ($offen as $b): ?>
            <div style="border:1px solid #e5e5ea;border-radius:12px;padding:14px;margin-bottom:16px">
                <div style="margin-bottom:8px">
                    <strong><?= Html::a(Html::encode($b->verein->name), $b->verein->getUrl()) ?></strong><br>
                    <small class="text-muted">von <?= Html::encode($b->user->displayName) ?> · <?= Yii::$app->formatter->asDatetime($b->created_at, 'short') ?></small>
                </div>
                <?= $this->render('_beitrag', ['beitrag' => $b]) ?>
                <?php $url = $space->createUrl('/vereine/beitrag/entscheiden', ['id' => $b->id]) ?>
                <?= Html::beginForm($url, 'post', ['style' => 'margin-top:14px']) ?>
                    <?= Html::hiddenInput('aktion', 'freigeben') ?>
                    <div style="display:flex;flex-wrap:wrap;gap:6px 18px;margin-bottom:8px">
                        <label style="font-weight:normal"><?= Html::checkbox('vereinsseite', true) ?> Auf der Vereinsseite zeigen</label>
                        <label style="font-weight:normal"><?= Html::checkbox('uebersicht', false) ?> In der Übersicht (Stream) zeigen</label>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm"><i class="fa fa-check"></i> Freigeben</button>
                <?= Html::endForm() ?>
                <details style="margin-top:10px">
                    <summary style="cursor:pointer" class="text-danger">Ablehnen …</summary>
                    <?= Html::beginForm($url, 'post', ['style' => 'margin-top:8px']) ?>
                        <?= Html::hiddenInput('aktion', 'ablehnen') ?>
                        <div class="form-group">
                            <label for="begruendung-<?= $b->id ?>">Begründung (geht per E-Mail an <?= Html::encode($b->user->displayName) ?>)</label>
                            <?= Html::textarea('begruendung', '', ['id' => 'begruendung-' . $b->id, 'class' => 'form-control', 'rows' => 3,
                                'maxlength' => 2000, 'required' => true]) ?>
                        </div>
                        <button type="submit" class="btn btn-danger btn-sm"><i class="fa fa-times"></i> Ablehnen und E-Mail senden</button>
                    <?= Html::endForm() ?>
                </details>
            </div>
        <?php endforeach ?>
    </div>
</div>

<?php if ($erledigt): ?>
<div class="panel panel-default">
    <div class="panel-heading"><strong>Zuletzt bearbeitet</strong></div>
    <div class="panel-body">
        <ul class="list-unstyled">
            <?php foreach ($erledigt as $b): ?>
                <li style="margin-bottom:10px;display:flex;gap:10px;align-items:flex-start">
                    <span style="flex:1;min-width:0">
                        <strong><?= Html::a(Html::encode($b->verein->name), $b->verein->getUrl()) ?></strong>
                        – <?= Html::encode(mb_strimwidth($b->text, 0, 80, '…')) ?><br>
                        <small class="text-muted">
                            <?= Html::encode($b->user->displayName ?? '') ?> ·
                            <?php if ($b->status === VereinBeitrag::STATUS_ABGELEHNT): ?>
                                abgelehnt: <?= Html::encode(mb_strimwidth((string)$b->begruendung, 0, 80, '…')) ?>
                            <?php else: ?>
                                veröffentlicht <?= implode(' und ', array_filter([$b->auf_vereinsseite ? 'auf der Vereinsseite' : '', $b->uebersicht ? 'in der Übersicht' : ''])) ?>
                                <?= $b->content ? ' · ' . Html::a('Beitrag ansehen', $b->content->getUrl()) : '' ?>
                            <?php endif ?>
                            · <?= $b->geprueft_am ? Yii::$app->formatter->asDatetime($b->geprueft_am, 'short') : '' ?>
                        </small>
                    </span>
                    <?= Html::a('<i class="fa fa-trash"></i>', $space->createUrl('/vereine/beitrag/entfernen', ['id' => $b->id]), [
                        'class' => 'btn btn-light btn-sm', 'title' => 'Entfernen', 'data-method' => 'post',
                        'data-confirm' => 'Diesen Eintrag ganz entfernen (auch aus Vereinsseite und Übersicht)?']) ?>
                </li>
            <?php endforeach ?>
        </ul>
    </div>
</div>
<?php endif ?>
