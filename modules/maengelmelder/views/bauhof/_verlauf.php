<?php
/**
 * Verlauf (Nachrichten in beide Richtungen + interne Vermerke)
 *
 * @var \humhub\modules\maengelmelder\models\BauhofNachricht[] $verlauf
 * @var \humhub\modules\space\models\Space $space
 * @var bool $mitAuftrag Auftragsnummer zu jeder Nachricht zeigen (Chat mit einem Mitarbeiter)
 */

use humhub\helpers\Html;
use humhub\modules\maengelmelder\models\BauhofNachricht;

$fmt = Yii::$app->formatter;
$haken = ['wartet' => ['fa-clock-o', 'wartet, bis der Mitarbeiter auf den Hinweis tippt'], 'gesendet' => ['fa-check', 'gesendet'],
    'zugestellt' => ['fa-check-circle-o', 'zugestellt'], 'gelesen' => ['fa-check-circle text-primary', 'gelesen'],
    'fehler' => ['fa-exclamation-triangle text-danger', 'nicht zugestellt']];
?>
<?php if ($verlauf === []): ?>
    <p class="text-muted small">Noch keine Nachrichten.</p>
<?php endif ?>
<?php foreach ($verlauf as $n):
    $wann = $fmt->asDatetime($n->created_at, 'short');
    $auftrag = $mitAuftrag && $n->content_id
        ? ' · ' . Html::a('Nr. ' . $n->content_id, $space->createUrl('/maengelmelder/bauhof/mangel', ['id' => $n->content_id])) : '';
    ?>
    <?php if ($n->richtung === BauhofNachricht::INTERN): ?>
        <div class="bauhof-msg bauhof-msg-intern">
            <?= $n->typ === 'status' ? '<i class="fa fa-flag"></i> ' : '<i class="fa fa-info-circle"></i> ' ?>
            <?= Html::encode($n->text) ?><?= $n->user && $n->typ !== 'status' ? ' – ' . Html::encode($n->user->displayName) : '' ?> · <?= $wann ?><?= $auftrag ?>
        </div>
    <?php else:
        $ein = $n->richtung === BauhofNachricht::EIN;
        $von = $ein ? ($n->mitarbeiter?->name ?? 'Mitarbeiter (gelöscht)') : ($n->user?->displayName ?? 'Bauhof (automatisch)');
        ?>
        <div class="bauhof-msg bauhof-msg-<?= $ein ? 'ein' : 'aus' ?>">
            <small><?= Html::encode($von) ?> · <?= $wann ?><?= $auftrag ?>
                <?php if (!$ein && isset($haken[$n->zustellung])): ?>
                    <i class="fa <?= $haken[$n->zustellung][0] ?>" title="<?= Html::encode($haken[$n->zustellung][1] . ($n->fehler ? ': ' . $n->fehler : '')) ?>"></i>
                <?php endif ?></small>
            <?php if ($n->typ === 'auftrag'): ?>
                <details><summary><i class="fa fa-whatsapp"></i> Auftrag per WhatsApp</summary>
                    <div style="white-space:pre-line"><?= Html::encode($n->text) ?></div></details>
            <?php else: ?>
                <?php if ($n->file_id && $n->isBild()): ?>
                    <a href="<?= Html::encode($space->createUrl('/maengelmelder/bauhof/datei', ['id' => $n->id])) ?>" target="_blank" rel="noopener">
                        <img src="<?= Html::encode($space->createUrl('/maengelmelder/bauhof/datei', ['id' => $n->id])) ?>" alt="Foto" loading="lazy"></a>
                <?php elseif ($n->file_id): ?>
                    <a href="<?= Html::encode($space->createUrl('/maengelmelder/bauhof/datei', ['id' => $n->id])) ?>" target="_blank" rel="noopener">
                        <i class="fa fa-file"></i> <?= Html::encode($n->file?->file_name ?? 'Datei') ?></a>
                <?php elseif ($n->media_id): ?>
                    <em class="text-muted">Foto wird geladen …</em>
                <?php endif ?>
                <?php if ($n->text !== null && $n->text !== ''): ?>
                    <div style="white-space:pre-line"><?= Html::encode($n->text) ?></div>
                <?php endif ?>
                <?php if ($n->zustellung === 'fehler' && $n->fehler): ?>
                    <small class="text-danger"><?= Html::encode($n->fehler) ?></small>
                <?php endif ?>
            <?php endif ?>
        </div>
    <?php endif ?>
<?php endforeach ?>
