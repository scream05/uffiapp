<?php
/**
 * @var \humhub\modules\whatsapp\models\Entwurf[] $offen
 * @var \humhub\modules\whatsapp\models\Nachricht[][] $ohne
 * @var \humhub\modules\whatsapp\models\Entwurf[] $erledigt
 * @var int $wartend
 * @var array $usage
 */

use humhub\helpers\Html;
use humhub\modules\whatsapp\components\Verarbeitung;
use humhub\modules\whatsapp\models\Entwurf;

$space = $this->context->contentContainer;
$bild = function (Entwurf $e) use ($space) {
    $alle = $e->getMedien();
    $n = isset($alle[$e->bild ?? -1]) && $alle[$e->bild]->isBild() ? $alle[$e->bild]
        : (array_values(array_filter($alle, fn($n) => $n->isBild()))[0] ?? null);
    return $n ? Html::img($space->createUrl('/whatsapp/eingang/datei', ['id' => $n->id]),
        ['style' => 'width:72px;height:72px;object-fit:cover;border-radius:8px', 'alt' => '']) : '';
};
?>
<div class="panel panel-default">
    <div class="panel-heading"><strong>WhatsApp-Eingang</strong> – Termine aus der WhatsApp-Community</div>
    <div class="panel-body">
        <p class="help-block">Beiträge, die an die Termin-Nummer weitergeleitet werden, liest eine KI aus. Bitte Angaben mit dem
            Plakat vergleichen und übernehmen – erst dann erscheint der Termin im Kalender.
            <?php if ($wartend): ?><br><strong><?= $wartend ?> Nachricht(en)</strong> werden gerade ausgewertet.<?php endif ?>
        </p>

        <?php if (!$offen && !$ohne): ?>
            <p class="text-muted">Nichts zu prüfen.</p>
        <?php endif ?>

        <?php foreach ($offen as $e): ?>
            <div class="d-flex gap-3 align-items-start" style="margin-bottom:14px">
                <?= $bild($e) ?>
                <div>
                    <?= Html::a('<strong>' . Html::encode($e->titel ?: '(ohne Titel)') . '</strong>',
                        $space->createUrl('/whatsapp/eingang/bearbeiten', ['id' => $e->id])) ?><br>
                    <?= $e->start_datum ? Html::encode(Verarbeitung::wann($e)) : '<span class="text-muted">Datum fehlt</span>' ?>
                    <?= $e->ort ? ' · ' . Html::encode($e->ort) : '' ?><br>
                    <small class="text-muted">von <?= Html::encode($e->absender_name ?: 'unbekannt') ?>,
                        <?= Yii::$app->formatter->asRelativeTime($e->created_at) ?></small>
                    <?php if ($e->hinweis): ?><br><small class="text-warning"><i class="fa fa-exclamation-triangle"></i> <?= Html::encode($e->hinweis) ?></small><?php endif ?>
                </div>
            </div>
        <?php endforeach ?>
    </div>
</div>

<?php if ($ohne): ?>
    <div class="panel panel-default">
        <div class="panel-heading"><strong>Ohne erkannten Termin</strong> (letzte 14 Tage)</div>
        <div class="panel-body">
            <?php foreach ($ohne as $gruppe => $nachrichten): ?>
                <div class="d-flex gap-3 align-items-start" style="margin-bottom:14px">
                    <?php foreach ($nachrichten as $n): ?>
                        <?php if ($n->isBild()): ?>
                            <?= Html::a(Html::img($space->createUrl('/whatsapp/eingang/datei', ['id' => $n->id]),
                                ['style' => 'width:72px;height:72px;object-fit:cover;border-radius:8px', 'alt' => '']),
                                $space->createUrl('/whatsapp/eingang/datei', ['id' => $n->id]), ['target' => '_blank']) ?>
                        <?php elseif ($n->datei): ?>
                            <?= Html::a('<i class="fa fa-file-pdf-o fa-2x"></i>', $space->createUrl('/whatsapp/eingang/datei', ['id' => $n->id]), ['target' => '_blank']) ?>
                        <?php endif ?>
                    <?php endforeach ?>
                    <div>
                        <small class="text-muted">von <?= Html::encode($nachrichten[0]->absender_name ?: 'unbekannt') ?>,
                            <?= Yii::$app->formatter->asRelativeTime($nachrichten[0]->empfangen_at) ?></small><br>
                        <?php foreach ($nachrichten as $n): ?>
                            <?php if ($n->text): ?><div style="white-space:pre-line"><?= Html::encode(mb_strimwidth($n->text, 0, 400, '…')) ?></div><?php endif ?>
                        <?php endforeach ?>
                        <small class="text-muted"><?= Html::encode($nachrichten[0]->ergebnis ?? $nachrichten[0]->fehler ?? '') ?></small><br>
                        <?= Html::a('Termin anlegen', $space->createUrl('/whatsapp/eingang/anlegen', ['gruppe' => $gruppe]),
                            ['class' => 'btn btn-sm btn-primary', 'data-method' => 'post']) ?>
                        <?= Html::a('Ausblenden', $space->createUrl('/whatsapp/eingang/verwerfen', ['gruppe' => $gruppe]),
                            ['class' => 'btn btn-sm btn-light', 'data-method' => 'post']) ?>
                    </div>
                </div>
            <?php endforeach ?>
        </div>
    </div>
<?php endif ?>

<?php if ($erledigt): ?>
    <div class="panel panel-default">
        <div class="panel-heading"><strong>Zuletzt bearbeitet</strong></div>
        <div class="panel-body">
            <ul class="list-unstyled" style="margin:0">
                <?php foreach ($erledigt as $e): ?>
                    <li>
                        <?php if ($e->status === Entwurf::STATUS_UEBERNOMMEN): ?>
                            <i class="fa fa-check text-success"></i>
                            <?php $entry = $e->calendar_entry_id ? \humhub\modules\calendar\models\CalendarEntry::findOne($e->calendar_entry_id) : null ?>
                            <?= $entry ? Html::a(Html::encode($e->titel), $entry->content->getUrl()) : Html::encode($e->titel) ?>
                        <?php else: ?>
                            <i class="fa fa-times text-muted"></i> <span class="text-muted"><?= Html::encode($e->titel ?: '(ohne Titel)') ?> – abgelehnt</span>
                        <?php endif ?>
                        <small class="text-muted"><?= $e->bearbeitet_at ? Yii::$app->formatter->asRelativeTime($e->bearbeitet_at) : '' ?></small>
                    </li>
                <?php endforeach ?>
            </ul>
        </div>
    </div>
<?php endif ?>

<p class="text-muted small">KI-Auswertungen heute: <?= (int)$usage[0] ?> von höchstens <?= (int)$this->context->module->tagesLimit ?>.
    Rohnachrichten und Telefonnummern werden <?= (int)$this->context->module->aufbewahrungTage ?> Tage nach der Bearbeitung gelöscht.</p>
